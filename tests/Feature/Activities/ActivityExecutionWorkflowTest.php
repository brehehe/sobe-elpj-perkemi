<?php

namespace Tests\Feature\Activities;

use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use App\Models\ActivityExecutionRecord;
use App\Models\Evidence;
use App\Models\Mandate;
use App\Models\Participant;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ActivityExecutionWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_pic_starts_planned_activity_and_receives_evidence_task(): void
    {
        Notification::fake();
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);
        SmartTask::factory()->create([
            'unit_id' => $unit->id,
            'activity_id' => $activity->id,
            'assignee_id' => $owner->id,
            'type' => 'START_ACTIVITY',
            'status' => TaskStatus::Open,
        ]);

        $response = $this->actingAs($owner)->post("/activities/{$activity->id}/starts");

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ActivityStatus::InProgress, $activity->fresh()->status);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $activity->id,
            'type' => 'START_ACTIVITY',
            'status' => TaskStatus::Done->value,
        ]);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $activity->id,
            'type' => 'EVIDENCE_CAPTURE',
            'status' => TaskStatus::Open->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $activity->id,
            'event_type' => 'activity.started',
            'from_status' => ActivityStatus::Planned->value,
            'to_status' => ActivityStatus::InProgress->value,
        ]);
        Notification::assertSentTo($owner, WorkflowTaskAssigned::class);
    }

    public function test_non_pic_manager_cannot_change_activity_lifecycle(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $finance = $this->userWithRole('Finance PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);

        $this->actingAs($finance)->post("/activities/{$activity->id}/starts")->assertForbidden();

        $this->assertSame(ActivityStatus::Planned, $activity->fresh()->status);
    }

    public function test_agenda_and_execution_records_follow_the_activity_state_machine(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);

        $this->actingAs($owner)->post("/activities/{$activity->id}/agenda-items", [
            'title' => 'Pembukaan dan pengarahan',
            'description' => 'Briefing keselamatan dan pembagian kelompok.',
            'location' => 'Aula utama',
            'starts_at' => now()->subHours(7)->toDateTimeString(),
            'ends_at' => now()->subHours(6)->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $agendaItem = $activity->agendaItems()->firstOrFail();

        $this->actingAs($owner)->patch("/activities/{$activity->id}/agenda-items/{$agendaItem->id}", [
            'status' => ActivityAgendaStatus::InProgress->value,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch("/activities/{$activity->id}/agenda-items/{$agendaItem->id}", [
            'status' => ActivityAgendaStatus::Completed->value,
            'notes' => 'Agenda selesai sesuai jadwal.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post("/activities/{$activity->id}/execution-records", [
            'type' => 'PROGRESS',
            'title' => 'Sesi utama selesai',
            'details' => 'Seluruh materi inti telah disampaikan.',
            'progress_percent' => 75,
            'occurred_at' => now()->subHours(2)->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(ActivityAgendaStatus::Completed, $agendaItem->fresh()->status);
        $this->assertSame($owner->id, $agendaItem->fresh()->completed_by);
        $record = $activity->executionRecords()->firstOrFail();
        $this->assertSame(ActivityExecutionStatus::Recorded, $record->status);
        $this->assertSame(75, $record->progress_percent);
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $activity->id, 'event_type' => 'activity.execution_recorded']);
    }

    public function test_completion_is_blocked_until_issue_is_resolved_then_creates_evaluation_task(): void
    {
        Notification::fake();
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);
        ActivityAgendaItem::factory()->create([
            'activity_id' => $activity->id,
            'starts_at' => now()->subHours(7),
            'ends_at' => now()->subHours(6),
            'status' => ActivityAgendaStatus::Completed,
            'completed_by' => $owner->id,
            'completed_at' => now()->subHours(6),
        ]);
        Participant::factory()->create(['activity_id' => $activity->id, 'attendance_status' => 'ATTENDED']);
        Evidence::factory()->create(['activity_id' => $activity->id, 'uploaded_by' => $owner->id, 'type' => 'PHOTO']);

        $this->actingAs($owner)->post("/activities/{$activity->id}/execution-records", [
            'type' => 'INCIDENT',
            'title' => 'Peralatan sempat terhenti',
            'details' => 'Proyektor tidak merespons selama lima menit.',
            'severity' => 'MEDIUM',
            'occurred_at' => now()->subHours(3)->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $incident = $activity->executionRecords()->firstOrFail();

        $this->actingAs($owner)
            ->post("/activities/{$activity->id}/completions")
            ->assertSessionHasErrors('issues');
        $this->assertSame(ActivityStatus::InProgress, $activity->fresh()->status);

        $this->actingAs($owner)->patch("/activities/{$activity->id}/execution-records/{$incident->id}", [
            'resolution_notes' => 'Kabel diganti dan sesi dilanjutkan tanpa kehilangan materi.',
        ])->assertSessionHasNoErrors();
        $response = $this->actingAs($owner)->post("/activities/{$activity->id}/completions");

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ActivityExecutionStatus::Resolved, $incident->fresh()->status);
        $this->assertSame(ActivityStatus::Completed, $activity->fresh()->status);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $activity->id,
            'type' => 'COMPLETE_EVALUATION',
            'status' => TaskStatus::Open->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $activity->id,
            'event_type' => 'activity.completed',
            'to_status' => ActivityStatus::Completed->value,
        ]);
        Notification::assertSentTo($owner, WorkflowTaskAssigned::class);
    }

    public function test_completion_reports_each_missing_operational_gate_without_mutation(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);

        $response = $this->actingAs($owner)->post("/activities/{$activity->id}/completions");

        $response->assertSessionHasErrors(['agenda', 'attendance', 'evidence']);
        $this->assertSame(ActivityStatus::InProgress, $activity->fresh()->status);
        $this->assertDatabaseMissing('smart_tasks', [
            'activity_id' => $activity->id,
            'type' => 'COMPLETE_EVALUATION',
        ]);
    }

    public function test_cross_unit_nested_execution_record_returns_not_found(): void
    {
        $firstUnit = Unit::factory()->create();
        $secondUnit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $firstUnit);
        $otherOwner = $this->userWithRole('Owner/PIC', $secondUnit);
        $activity = $this->activityFor($firstUnit, $owner, ActivityStatus::InProgress);
        $record = ActivityExecutionRecord::factory()->create([
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'type' => 'INCIDENT',
            'status' => ActivityExecutionStatus::Open,
        ]);

        $this->actingAs($otherOwner)->patch("/activities/{$activity->id}/execution-records/{$record->id}", [
            'resolution_notes' => 'Percobaan akses lintas unit.',
        ])->assertNotFound();

        $this->assertSame(ActivityExecutionStatus::Open, $record->fresh()->status);
    }

    public function test_api_update_cannot_bypass_lifecycle_with_status_field(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);

        $this->actingAs($owner)->patchJson("/api/v1/activities/{$activity->id}", [
            'status' => ActivityStatus::Completed->value,
            'budget_ceiling' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'budget_ceiling']);

        $activity->refresh();
        $this->assertSame(ActivityStatus::Planned, $activity->status);
        $this->assertNotSame('1.00', $activity->budget_ceiling);
    }

    public function test_activity_cannot_start_before_its_schedule(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);
        $activity->update([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->actingAs($owner)
            ->post("/activities/{$activity->id}/starts")
            ->assertSessionHasErrors('status');

        $this->assertSame(ActivityStatus::Planned, $activity->fresh()->status);
        $this->assertDatabaseMissing('audit_events', [
            'auditable_id' => $activity->id,
            'event_type' => 'activity.started',
        ]);
    }

    public function test_agenda_outside_activity_schedule_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);

        $this->actingAs($owner)->post("/activities/{$activity->id}/agenda-items", [
            'title' => 'Agenda di luar jadwal',
            'starts_at' => now()->subDays(2)->toDateTimeString(),
            'ends_at' => now()->subDay()->toDateTimeString(),
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame(0, $activity->agendaItems()->count());
    }

    public function test_versioned_api_creates_agenda_with_the_shared_rules(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::Planned);

        $response = $this->actingAs($owner)->postJson("/api/v1/activities/{$activity->id}/agenda-items", [
            'title' => 'Agenda melalui API',
            'starts_at' => now()->subHours(7)->toDateTimeString(),
            'ends_at' => now()->subHours(6)->toDateTimeString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Agenda melalui API');
        $this->assertDatabaseHas('activity_agenda_items', [
            'activity_id' => $activity->id,
            'title' => 'Agenda melalui API',
        ]);
    }

    public function test_incident_requires_a_severity(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);

        $this->actingAs($owner)->post("/activities/{$activity->id}/execution-records", [
            'type' => 'INCIDENT',
            'title' => 'Insiden tanpa severity',
            'occurred_at' => now()->subHours(2)->toDateTimeString(),
        ])->assertSessionHasErrors('severity');

        $this->assertSame(0, $activity->executionRecords()->count());
    }

    public function test_progress_percentage_must_be_within_zero_and_one_hundred(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);

        $this->actingAs($owner)->post("/activities/{$activity->id}/execution-records", [
            'type' => 'PROGRESS',
            'title' => 'Progres tidak valid',
            'progress_percent' => 101,
            'occurred_at' => now()->subHours(2)->toDateTimeString(),
        ])->assertSessionHasErrors('progress_percent');

        $this->assertSame(0, $activity->executionRecords()->count());
    }

    public function test_execution_record_outside_activity_schedule_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $activity = $this->activityFor($unit, $owner, ActivityStatus::InProgress);

        $this->actingAs($owner)->post("/activities/{$activity->id}/execution-records", [
            'type' => 'NOTE',
            'title' => 'Catatan di luar jadwal',
            'occurred_at' => now()->subDays(3)->toDateTimeString(),
        ])->assertSessionHasErrors('occurred_at');

        $this->assertSame(0, $activity->executionRecords()->count());
    }

    private function activityFor(Unit $unit, User $owner, ActivityStatus $status): Activity
    {
        $mandate = Mandate::factory()->create([
            'unit_id' => $unit->id,
            'created_by' => $owner->id,
        ]);

        return Activity::factory()->create([
            'unit_id' => $unit->id,
            'mandate_id' => $mandate->id,
            'pic_id' => $owner->id,
            'starts_at' => now()->subHours(8),
            'ends_at' => now()->subHour(),
            'status' => $status,
        ]);
    }
}
