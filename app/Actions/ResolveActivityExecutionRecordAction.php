<?php

namespace App\Actions;

use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityExecutionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveActivityExecutionRecordAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Activity $activity, ActivityExecutionRecord $record, User $user, string $resolutionNotes): ActivityExecutionRecord
    {
        return DB::transaction(function () use ($activity, $record, $user, $resolutionNotes): ActivityExecutionRecord {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $lockedRecord = ActivityExecutionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($lockedRecord->activity_id !== $lockedActivity->id) {
                abort(404);
            }
            if ($lockedActivity->status !== ActivityStatus::InProgress
                || ! in_array($lockedRecord->type, [ActivityExecutionType::Incident, ActivityExecutionType::Change], true)
                || $lockedRecord->status !== ActivityExecutionStatus::Open) {
                throw ValidationException::withMessages(['resolution_notes' => 'Hanya issue terbuka pada kegiatan berjalan yang dapat diselesaikan.']);
            }

            $lockedRecord->update([
                'status' => ActivityExecutionStatus::Resolved,
                'resolved_by' => $user->id,
                'resolution_notes' => $resolutionNotes,
                'resolved_at' => now(),
            ]);
            $this->recordAuditEvent->handle($lockedActivity, 'activity.issue_resolved', $user, ActivityExecutionStatus::Open->value, ActivityExecutionStatus::Resolved->value, [
                'execution_record_id' => $lockedRecord->id,
            ]);

            return $lockedRecord->fresh(['resolver']);
        }, attempts: 3);
    }
}
