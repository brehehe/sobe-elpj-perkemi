<?php

namespace Tests\Feature\Budgets;

use App\Models\Budget;
use App\Models\Mandate;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class BudgetWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_rab_requires_an_approved_plan_and_calculates_total_on_the_server(): void
    {
        $unit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);
        $reviewer = $this->userWithRole('Approver', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $finance->id]);
        $plan = Plan::factory()->approved()->create(['unit_id' => $unit->id, 'owner_id' => $finance->id, 'mandate_id' => $mandate->id]);
        PlanVersion::factory()->create(['plan_id' => $plan->id, 'created_by' => $finance->id, 'status' => 'APPROVED']);
        $payload = [
            'plan_id' => $plan->id,
            'code' => 'RAB-TEST-001',
            'currency' => 'IDR',
            'lines' => [
                ['account_code' => '521211', 'description' => 'Konsumsi', 'quantity' => 10, 'unit' => 'orang', 'unit_cost' => 25_000],
                ['account_code' => '524111', 'description' => 'Transportasi', 'quantity' => 2, 'unit' => 'orang', 'unit_cost' => 100_000],
            ],
        ];

        $this->actingAs($finance)->post('/budgets', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $budget = Budget::query()->where('code', 'RAB-TEST-001')->firstOrFail();
        $this->assertSame('450000.00', $budget->currentVersionRecord->total);
        $this->assertCount(2, $budget->currentVersionRecord->lines);

        $this->actingAs($reviewer)->post("/budgets/{$budget->id}/reviews", ['decision' => 'APPROVED'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $budget->fresh()->status->value);
    }

    public function test_submitted_planning_is_available_for_rab_but_rab_review_waits_for_plan_approval(): void
    {
        $unit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);
        $reviewer = $this->userWithRole('Approver', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $finance->id]);
        $plan = Plan::factory()->create([
            'unit_id' => $unit->id,
            'owner_id' => $finance->id,
            'mandate_id' => $mandate->id,
            'status' => 'SUBMITTED',
            'current_version' => 1,
        ]);
        PlanVersion::factory()->create([
            'plan_id' => $plan->id,
            'created_by' => $finance->id,
            'status' => 'SUBMITTED',
            'version' => 1,
        ]);

        $this->actingAs($finance)->get('/budgets/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('budgets/create')
                ->where('plans.0.id', $plan->id));

        $this->actingAs($finance)->post('/budgets', [
            'plan_id' => $plan->id,
            'code' => 'RAB-SUBMITTED-PLAN',
            'currency' => 'IDR',
            'lines' => [[
                'account_code' => '521211',
                'description' => 'Konsumsi',
                'quantity' => 10,
                'unit' => 'orang',
                'unit_cost' => 25_000,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $budget = Budget::query()->where('code', 'RAB-SUBMITTED-PLAN')->firstOrFail();
        $this->actingAs($reviewer)->post("/budgets/{$budget->id}/reviews", ['decision' => 'APPROVED'])
            ->assertSessionHasErrors(['decision' => 'Planning harus disetujui sebelum keputusan review RAB dicatat.']);
        $this->assertSame('SUBMITTED', $budget->fresh()->status->value);
    }
}
