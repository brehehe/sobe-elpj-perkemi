<?php

namespace Tests\Feature\Finance;

use App\Actions\SubmitLpjAction;
use App\Enums\ReconciliationStatus;
use App\Models\FinancialTransaction;
use App\Models\ReconciliationException;
use App\Models\SmartTask;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ReconciliationExceptionApprovalTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_finance_pic_requests_official_exception_without_opening_submission_gate(): void
    {
        [$workflow, $finance, $transaction, $exception] = $this->exceptionWorkflow();

        $response = $this->actingAs($finance)->patch("/reconciliation-exceptions/{$exception->id}", [
            'resolution_type' => 'APPROVED_EXCEPTION',
            'resolution' => 'Selisih berasal dari biaya lokasi yang disetujui setelah RAB diterbitkan.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => $exception->id,
            'status' => 'PENDING_APPROVAL',
            'requested_by' => $finance->id,
            'decision' => null,
            'is_approved_exception' => false,
            'authority_policy_version' => 'reconciliation-exception-authority-v1',
        ]);
        $this->assertSame(ReconciliationStatus::Exception, $transaction->fresh()->reconciliation_status);

        $this->actingAs($workflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}")
            ->assertInertia(fn ($page) => $page
                ->where('composer.ready', false)
                ->has('composer.errors.reconciliation'));
    }

    public function test_authorized_approver_accepts_exception_and_records_authority_metadata(): void
    {
        [$workflow, $finance, $transaction, $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $approver = $this->userWithRole('Approver', $workflow['unit']);

        $response = $this->actingAs($approver)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Pengecualian proporsional, terdokumentasi, dan berada dalam kewenangan unit.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => $exception->id,
            'status' => 'APPROVED_EXCEPTION',
            'decision' => 'APPROVED',
            'decided_by' => $approver->id,
            'is_approved_exception' => true,
            'authority_policy_version' => 'reconciliation-exception-authority-v1',
        ]);
        $this->assertSame(ReconciliationStatus::Resolved, $transaction->fresh()->reconciliation_status);
        $this->assertSame('DONE', SmartTask::query()->where('metadata->reconciliation_exception_id', $exception->id)->firstOrFail()->status->value);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $exception->id,
            'event_type' => 'finance.reconciliation_exception_decided',
            'to_status' => 'APPROVED_EXCEPTION',
        ]);
    }

    public function test_rejected_exception_remains_blocking_and_can_be_reworked(): void
    {
        [$workflow, $finance, $transaction, $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $approver = $this->userWithRole('Approver', $workflow['unit']);

        $this->actingAs($approver)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'REJECTED',
            'notes' => 'Bukti perubahan biaya belum dilampirkan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('REJECTED', $exception->fresh()->status->value);
        $this->assertSame(ReconciliationStatus::Exception, $transaction->fresh()->reconciliation_status);

        $this->actingAs($finance)->patch("/reconciliation-exceptions/{$exception->id}", [
            'resolution_type' => 'CLARIFIED',
            'resolution' => 'Bukti perubahan biaya telah dilampirkan dan sumber telah diklarifikasi.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('RESOLVED', $exception->fresh()->status->value);
        $this->assertSame(ReconciliationStatus::Resolved, $transaction->fresh()->reconciliation_status);
    }

    public function test_requester_cannot_decide_their_own_exception(): void
    {
        [, $finance, , $exception] = $this->exceptionWorkflow();
        $finance->givePermissionTo('finance.approve-exception');
        $this->requestApproval($finance, $exception);

        $response = $this->actingAs($finance)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Mencoba menyetujui permintaan sendiri.',
        ]);

        $response->assertRedirect()->assertSessionHasErrors('approver');
        $this->assertSame('PENDING_APPROVAL', $exception->fresh()->status->value);
        $this->assertNull($exception->fresh()->decision);
    }

    public function test_successful_reconciliation_supersedes_pending_exception_request(): void
    {
        [$workflow, $finance, $transaction, $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $transaction->update(['amount' => 1_250_000]);
        $budgetLine = $workflow['budget']->currentVersionRecord->lines->firstOrFail();

        $this->actingAs($finance)->post("/transactions/{$transaction->id}/reconciliations", [
            'budget_line_id' => $budgetLine->id,
            'notes' => 'Data sumber telah disinkronkan ulang dan sekarang cocok.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $exception->refresh();
        $this->assertSame('RESOLVED', $exception->status->value);
        $this->assertSame('RECONCILED', $exception->resolution_type);
        $this->assertNull($exception->decision);
        $this->assertSame(ReconciliationStatus::Matched, $transaction->fresh()->reconciliation_status);
        $this->assertSame('DONE', SmartTask::query()->where('metadata->reconciliation_exception_id', $exception->id)->firstOrFail()->status->value);
    }

    public function test_user_without_exception_authority_cannot_record_decision(): void
    {
        [$workflow, $finance, , $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $this->actingAs($verifier)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Tidak memiliki kewenangan exception.',
        ])->assertForbidden();

        $this->assertSame('PENDING_APPROVAL', $exception->fresh()->status->value);
    }

    public function test_inactive_approver_cannot_record_exception_decision(): void
    {
        [$workflow, $finance, , $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $approver->update(['is_active' => false]);

        $this->actingAs($approver)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Kewenangan pengguna tidak lagi aktif.',
        ])->assertForbidden();

        $this->assertSame('PENDING_APPROVAL', $exception->fresh()->status->value);
    }

    public function test_other_unit_cannot_discover_exception_decision_endpoint(): void
    {
        [, $finance, , $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $otherWorkflow = $this->completeWorkflow();
        $otherApprover = $this->userWithRole('Approver', $otherWorkflow['unit']);

        $this->actingAs($otherApprover)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Keputusan lintas unit tidak diizinkan.',
        ])->assertNotFound();

        $this->assertSame('PENDING_APPROVAL', $exception->fresh()->status->value);
    }

    public function test_api_returns_exception_decision_contract(): void
    {
        [$workflow, $finance, , $exception] = $this->exceptionWorkflow();
        $this->actingAs($finance)->patchJson("/api/v1/reconciliation-exceptions/{$exception->id}", [
            'resolution_type' => 'APPROVED_EXCEPTION',
            'resolution' => 'Permintaan melalui API dengan alasan yang terdokumentasi.',
        ])->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL');
        $approver = $this->userWithRole('Approver', $workflow['unit']);

        $this->actingAs($approver)->postJson("/api/v1/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Disetujui melalui kontrak API v1.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'APPROVED_EXCEPTION')
            ->assertJsonPath('data.decision', 'APPROVED')
            ->assertJsonPath('data.decision_maker.id', $approver->id)
            ->assertJsonPath('data.authority_policy_version', 'reconciliation-exception-authority-v1');
    }

    public function test_submission_snapshot_contains_approved_exception_decision(): void
    {
        [$workflow, $finance, , $exception] = $this->exceptionWorkflow();
        $this->requestApproval($finance, $exception);
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $this->actingAs($approver)->post("/reconciliation-exceptions/{$exception->id}/decisions", [
            'decision' => 'APPROVED',
            'notes' => 'Disetujui untuk dibekukan pada snapshot LPJ.',
        ])->assertSessionHasNoErrors();

        $version = app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);
        $snapshotException = collect($version->snapshot['transactions'])
            ->flatMap(fn (array $transaction): array => $transaction['reconciliation']['exceptions'] ?? [])
            ->firstWhere('id', $exception->id);

        $this->assertSame('APPROVED_EXCEPTION', $snapshotException['status']);
        $this->assertSame('APPROVED', $snapshotException['decision']);
        $this->assertSame('reconciliation-exception-authority-v1', $snapshotException['authority_policy_version']);
        $this->assertSame('WARNING', collect($version->snapshot['system_checks'])->firstWhere('item', 'Pengecualian rekonsiliasi resmi')['result']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: User, 2: FinancialTransaction, 3: ReconciliationException}
     */
    private function exceptionWorkflow(): array
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
            'notes' => 'Pemeriksaan menemukan selisih.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return [
            $workflow,
            $finance,
            $transaction,
            ReconciliationException::query()->whereHas('reconciliation', fn ($query) => $query->where('financial_transaction_id', $transaction->id))->firstOrFail(),
        ];
    }

    private function requestApproval(User $finance, ReconciliationException $exception): void
    {
        $this->actingAs($finance)->patch("/reconciliation-exceptions/{$exception->id}", [
            'resolution_type' => 'APPROVED_EXCEPTION',
            'resolution' => 'Selisih dimohonkan sebagai pengecualian resmi dengan bukti pendukung.',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
}
