<?php

namespace Tests\Feature\ActionCenter;

use App\Enums\TaskStatus;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ActionCenterTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/action-center')->assertRedirect('/login');
    }

    public function test_user_without_action_center_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/action-center')->assertForbidden();
    }

    public function test_local_user_only_sees_assigned_or_capability_tasks_in_their_scope(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);
        $otherFinance = $this->userWithRole('Finance PIC', $unit);
        $assigned = SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => $finance->id,
            'type' => 'FOLLOW_UP',
            'title' => 'Tugas langsung finance',
        ]);
        $capability = SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => null,
            'recipient_permission' => 'finance.resolve-exception',
            'type' => 'RECONCILIATION_EXCEPTION',
            'title' => 'Exception unit sendiri',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => $otherFinance->id,
            'type' => 'FOLLOW_UP',
            'title' => 'Tugas pengguna lain',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => null,
            'recipient_permission' => 'lpjs.verify.administration',
            'type' => 'VERIFY_LPJ_ADMINISTRATION',
            'title' => 'Bukan capability finance',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $otherUnit->id,
            'assignee_id' => null,
            'recipient_permission' => 'finance.resolve-exception',
            'type' => 'RECONCILIATION_EXCEPTION',
            'title' => 'Exception unit lain',
        ]);

        $this->actingAs($finance)->get('/action-center')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('action-center/index')
                ->where('scope.can_view_across_units', false)
                ->has('tasks.data', 2)
                ->where('tasks.data.0.id', fn (string $id): bool => in_array($id, [$assigned->id, $capability->id], true))
                ->where('tasks.data.1.id', fn (string $id): bool => in_array($id, [$assigned->id, $capability->id], true))
                ->where('metrics.open', 2));
    }

    public function test_pb_verifier_sees_global_pb_task_but_not_other_units_local_queue(): void
    {
        $pbUnit = Unit::factory()->create();
        $activityUnit = Unit::factory()->create();
        $pbVerifier = $this->userWithRole('PB Verifier', $pbUnit);
        $pbTask = SmartTask::factory()->create([
            'unit_id' => $activityUnit->id,
            'assignee_id' => null,
            'recipient_permission' => 'lpjs.pb_verify',
            'type' => 'PB_VERIFY_LPJ',
            'title' => 'Verifikasi nasional',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $activityUnit->id,
            'assignee_id' => null,
            'recipient_permission' => 'lpjs.approve',
            'type' => 'APPROVE_LPJ',
            'title' => 'Approval unit lain',
        ]);

        $this->actingAs($pbVerifier)->get('/action-center')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_across_units', true)
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $pbTask->id));
    }

    public function test_dashboard_uses_the_same_recipient_scope_as_action_center(): void
    {
        $unit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);
        $visible = SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => null,
            'recipient_permission' => 'finance.resolve-exception',
            'type' => 'RECONCILIATION_EXCEPTION',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => null,
            'recipient_permission' => 'lpjs.verify.administration',
            'type' => 'VERIFY_LPJ_ADMINISTRATION',
        ]);

        $this->actingAs($finance)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.id', $visible->id));
    }

    public function test_filters_are_server_side_and_rows_expose_sla_completion_and_action_contract(): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        $unit = Unit::factory()->create(['code' => 'UNIT-A', 'name' => 'Unit A']);
        $finance = $this->userWithRole('Finance PIC', $unit);
        $workflow = $this->completeWorkflow($unit, $finance);
        $overdue = SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'activity_id' => $workflow['activity']->id,
            'assignee_id' => $finance->id,
            'subject_type' => 'RECONCILIATION_EXCEPTION',
            'subject_id' => fake()->uuid(),
            'type' => 'RECONCILIATION_EXCEPTION',
            'title' => 'Selesaikan selisih bank Oktober',
            'priority' => 'CRITICAL',
            'status' => TaskStatus::Open,
            'due_at' => now()->subHour(),
            'completion_condition' => 'Selisih memperoleh resolusi resmi.',
            'escalation_path' => 'Finance PIC lalu approver.',
        ]);
        SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'assignee_id' => $finance->id,
            'title' => 'Tugas lain',
            'priority' => 'MEDIUM',
            'due_at' => now()->addWeek(),
        ]);

        $this->actingAs($finance)
            ->get('/action-center?q=Oktober&priority=CRITICAL&due=overdue&sort=due_at&direction=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'Oktober')
                ->where('filters.priority', 'CRITICAL')
                ->where('filters.due', 'overdue')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $overdue->id)
                ->where('tasks.data.0.sla.status', 'OVERDUE')
                ->where('tasks.data.0.completion_condition', 'Selisih memperoleh resolusi resmi.')
                ->where('tasks.data.0.escalation_path', 'Finance PIC lalu approver.')
                ->where('tasks.data.0.action_href', '/finance')
                ->where('tasks.data.0.object.label', $workflow['activity']->code));
    }

    public function test_local_user_cannot_probe_another_unit_filter(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);

        $this->actingAs($finance)
            ->get('/action-center?unit='.$otherUnit->id)
            ->assertNotFound();
    }

    public function test_invalid_sort_is_rejected_before_it_reaches_the_query(): void
    {
        $unit = Unit::factory()->create();
        $finance = $this->userWithRole('Finance PIC', $unit);

        $this->actingAs($finance)
            ->from('/action-center')
            ->get('/action-center?sort=due_at%20desc%2C%20status')
            ->assertRedirect('/action-center')
            ->assertSessionHasErrors(['sort' => 'Urutan tugas tidak dikenali.']);
    }
}
