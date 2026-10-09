<?php

namespace Tests\Concerns;

use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityStatus;
use App\Enums\LpjStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use App\Models\ActivityExecutionRecord;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\FinancialTransaction;
use App\Models\Lpj;
use App\Models\Mandate;
use App\Models\Participant;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

trait BuildsWorkflow
{
    protected function seedPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(string $role, Unit $unit): User
    {
        $user = User::factory()->forUnit($unit)->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{unit: Unit, owner: User, activity: Activity, lpj: Lpj} */
    protected function completeWorkflow(?Unit $unit = null, ?User $owner = null): array
    {
        $unit ??= Unit::factory()->create();
        $owner ??= $this->userWithRole('Owner/PIC', $unit);
        $mandate = Mandate::factory()->create([
            'unit_id' => $unit->id,
            'created_by' => $owner->id,
            'status' => 'ACTIVE',
        ]);
        ['plan' => $plan, 'budget' => $budget, 'budgetLine' => $budgetLine] = $this->approvedSources($unit, $mandate, $owner);
        $activity = Activity::factory()->create([
            'unit_id' => $unit->id,
            'mandate_id' => $mandate->id,
            'plan_id' => $plan->id,
            'budget_id' => $budget->id,
            'pic_id' => $owner->id,
            'status' => ActivityStatus::Completed,
        ]);
        $lpj = Lpj::factory()->create([
            'activity_id' => $activity->id,
            'owner_id' => $owner->id,
            'status' => LpjStatus::Draft,
        ]);
        ActivityAgendaItem::factory()->create([
            'activity_id' => $activity->id,
            'completed_by' => $owner->id,
            'status' => ActivityAgendaStatus::Completed,
            'completed_at' => $activity->ends_at,
        ]);
        ActivityExecutionRecord::factory()->create([
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
        ]);
        Participant::factory()->create(['activity_id' => $activity->id]);
        FinancialTransaction::factory()->create([
            'activity_id' => $activity->id,
            'budget_line_id' => $budgetLine->id,
            'recorded_by' => $owner->id,
            'amount' => 1_250_000,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        Evaluation::factory()->create(['activity_id' => $activity->id, 'created_by' => $owner->id]);
        $path = 'evidence/'.$activity->id.'/test.pdf';
        Storage::disk('local')->put($path, 'verified evidence');
        Evidence::factory()->create([
            'activity_id' => $activity->id,
            'uploaded_by' => $owner->id,
            'path' => $path,
            'size' => strlen('verified evidence'),
            'sha256' => hash('sha256', 'verified evidence'),
        ]);

        return compact('unit', 'owner', 'plan', 'budget', 'activity', 'lpj');
    }

    /** @return array{plan: Plan, budget: Budget, budgetLine: BudgetLine} */
    protected function approvedSources(Unit $unit, Mandate $mandate, User $owner): array
    {
        $plan = Plan::factory()->approved()->create([
            'mandate_id' => $mandate->id,
            'unit_id' => $unit->id,
            'owner_id' => $owner->id,
        ]);
        PlanVersion::factory()->create([
            'plan_id' => $plan->id,
            'created_by' => $owner->id,
            'status' => 'APPROVED',
        ]);
        $budget = Budget::factory()->approved()->create([
            'plan_id' => $plan->id,
            'unit_id' => $unit->id,
            'owner_id' => $owner->id,
        ]);
        $budgetVersion = BudgetVersion::factory()->create([
            'budget_id' => $budget->id,
            'created_by' => $owner->id,
            'status' => 'APPROVED',
            'total' => 1_250_000,
        ]);
        $budgetLine = BudgetLine::factory()->create([
            'budget_version_id' => $budgetVersion->id,
            'quantity' => 1,
            'unit_cost' => 1_250_000,
            'subtotal' => 1_250_000,
        ]);

        return compact('plan', 'budget', 'budgetLine');
    }
}
