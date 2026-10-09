<?php

namespace Tests\Feature\Finance;

use App\Enums\ReconciliationStatus;
use App\Models\FinancialTransaction;
use App\Models\ReconciliationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ReconciliationWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_amount_mismatch_creates_an_exception_that_can_be_resolved(): void
    {
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        $budgetLine = $workflow['budget']->currentVersionRecord->lines->firstOrFail();
        $transaction = FinancialTransaction::factory()->create([
            'activity_id' => $workflow['activity']->id,
            'recorded_by' => $finance->id,
            'amount' => 900_000,
            'reconciliation_status' => ReconciliationStatus::Pending,
        ]);

        $this->actingAs($finance)->post("/transactions/{$transaction->id}/reconciliations", [
            'budget_line_id' => $budgetLine->id,
            'notes' => 'Pemeriksaan pertama.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('EXCEPTION', $transaction->fresh()->reconciliation_status->value);
        $exception = ReconciliationException::query()->firstOrFail();
        $this->assertSame('OPEN', $exception->status->value);
        $this->actingAs($finance)->patch("/reconciliation-exceptions/{$exception->id}", [
            'resolution_type' => 'CLARIFIED',
            'resolution' => 'Selisih telah dijelaskan dengan dokumen sumber yang lengkap.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('RESOLVED', $exception->fresh()->status->value);
        $this->assertSame('RESOLVED', $transaction->fresh()->reconciliation_status->value);
    }
}
