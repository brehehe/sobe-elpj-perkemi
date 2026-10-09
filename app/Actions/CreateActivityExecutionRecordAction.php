<?php

namespace App\Actions;

use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityExecutionRecord;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateActivityExecutionRecordAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Activity $activity, User $user, array $data): ActivityExecutionRecord
    {
        return DB::transaction(function () use ($activity, $user, $data): ActivityExecutionRecord {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($lockedActivity->status !== ActivityStatus::InProgress) {
                throw ValidationException::withMessages(['type' => 'Catatan pelaksanaan hanya dapat dibuat ketika kegiatan sedang berjalan.']);
            }

            $occurredAt = Date::parse($data['occurred_at']);
            if ($occurredAt->lt($lockedActivity->starts_at) || $occurredAt->gt($lockedActivity->ends_at)) {
                throw ValidationException::withMessages(['occurred_at' => 'Waktu catatan harus berada di dalam jadwal kegiatan.']);
            }

            $type = ActivityExecutionType::from($data['type']);
            $record = $lockedActivity->executionRecords()->create([
                ...$data,
                'created_by' => $user->id,
                'type' => $type,
                'status' => in_array($type, [ActivityExecutionType::Incident, ActivityExecutionType::Change], true)
                    ? ActivityExecutionStatus::Open
                    : ActivityExecutionStatus::Recorded,
            ]);
            $this->recordAuditEvent->handle($lockedActivity, 'activity.execution_recorded', $user, payload: [
                'execution_record_id' => $record->id,
                'type' => $type->value,
                'severity' => $record->severity,
                'progress_percent' => $record->progress_percent,
            ]);

            return $record;
        }, attempts: 3);
    }
}
