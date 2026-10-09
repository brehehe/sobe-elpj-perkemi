<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use App\Services\ActivityCompletionGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteActivityAction
{
    public function __construct(
        private ActivityCompletionGate $completionGate,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(Activity $activity, User $user): Activity
    {
        return DB::transaction(function () use ($activity, $user): Activity {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($lockedActivity->status !== ActivityStatus::InProgress) {
                throw ValidationException::withMessages(['status' => 'Hanya kegiatan yang sedang berjalan yang dapat diselesaikan.']);
            }

            $readiness = $this->completionGate->evaluate($lockedActivity);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages($readiness['errors']);
            }

            $lockedActivity->update(['status' => ActivityStatus::Completed]);
            SmartTask::query()
                ->where('activity_id', $lockedActivity->id)
                ->where('type', 'EVIDENCE_CAPTURE')
                ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                ->update(['status' => TaskStatus::Done]);

            $task = $this->createNextTask($lockedActivity);
            if ($task !== null) {
                DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            }

            $this->recordAuditEvent->handle($lockedActivity, 'activity.completed', $user, ActivityStatus::InProgress->value, ActivityStatus::Completed->value, [
                'completion_policy_version' => $readiness['policy_version'],
                'checks' => $readiness['checks'],
            ]);

            return $lockedActivity->fresh();
        }, attempts: 3);
    }

    private function createNextTask(Activity $activity): ?SmartTask
    {
        if ($activity->evaluations()->doesntExist()) {
            return SmartTask::create([
                'unit_id' => $activity->unit_id,
                'activity_id' => $activity->id,
                'assignee_id' => $activity->pic_id,
                'type' => 'COMPLETE_EVALUATION',
                'title' => 'Lengkapi evaluasi '.$activity->title,
                'description' => 'Catat capaian output, outcome, dan pembelajaran sebelum mengajukan LPJ.',
                'priority' => 'HIGH',
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => now()->addDays(3),
            ]);
        }

        return SmartTask::create([
            'unit_id' => $activity->unit_id,
            'activity_id' => $activity->id,
            'assignee_id' => $activity->pic_id,
            'type' => 'COMPLETE_LPJ',
            'title' => 'Lengkapi dan ajukan LPJ '.$activity->title,
            'description' => 'Periksa data sumber, rekonsiliasi, dan evaluasi sebelum membuat snapshot LPJ.',
            'priority' => 'HIGH',
            'status' => TaskStatus::Open,
            'source' => 'WORKFLOW',
            'due_at' => now()->addDays(7),
        ]);
    }
}
