<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartActivityAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Activity $activity, User $user): Activity
    {
        return DB::transaction(function () use ($activity, $user): Activity {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($lockedActivity->status !== ActivityStatus::Planned) {
                throw ValidationException::withMessages(['status' => 'Hanya kegiatan berstatus rencana yang dapat dimulai.']);
            }
            if ($lockedActivity->starts_at->isFuture()) {
                throw ValidationException::withMessages(['status' => 'Kegiatan belum dapat dimulai sebelum jadwal pelaksanaan.']);
            }

            $lockedActivity->update(['status' => ActivityStatus::InProgress]);
            SmartTask::query()
                ->where('activity_id', $lockedActivity->id)
                ->where('type', 'START_ACTIVITY')
                ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                ->update(['status' => TaskStatus::Done]);
            $task = SmartTask::create([
                'unit_id' => $lockedActivity->unit_id,
                'activity_id' => $lockedActivity->id,
                'assignee_id' => $lockedActivity->pic_id,
                'type' => 'EVIDENCE_CAPTURE',
                'title' => 'Lengkapi bukti selama '.$lockedActivity->title,
                'description' => 'Unggah bukti operasional dan perbarui agenda sebelum menyelesaikan kegiatan.',
                'priority' => 'HIGH',
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => $lockedActivity->ends_at,
                'metadata' => ['completion_policy_version' => config('activity.completion_policy.version')],
            ]);
            DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));

            $this->recordAuditEvent->handle($lockedActivity, 'activity.started', $user, ActivityStatus::Planned->value, ActivityStatus::InProgress->value, [
                'scheduled_start' => $lockedActivity->starts_at->toIso8601String(),
                'scheduled_end' => $lockedActivity->ends_at->toIso8601String(),
            ]);

            return $lockedActivity->fresh(['agendaItems', 'executionRecords']);
        }, attempts: 3);
    }
}
