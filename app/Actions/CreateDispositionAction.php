<?php

namespace App\Actions;

use App\Enums\DispositionStatus;
use App\Enums\TaskStatus;
use App\Models\Disposition;
use App\Models\Lpj;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDispositionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data): Disposition
    {
        return DB::transaction(function () use ($lpj, $user, $data): Disposition {
            $lockedLpj = Lpj::query()->with('activity')->lockForUpdate()->findOrFail($lpj->id);
            $version = $lockedLpj->currentVersionRecord()->first();
            if ($version === null) {
                throw ValidationException::withMessages(['lpj' => 'Disposisi hanya dapat dibuat setelah LPJ memiliki snapshot.']);
            }

            $disposition = Disposition::create([
                ...$data,
                'lpj_version_id' => $version->id,
                'unit_id' => $lockedLpj->activity->unit_id,
                'from_user_id' => $user->id,
                'status' => DispositionStatus::Open,
            ]);
            $task = SmartTask::create([
                'unit_id' => $lockedLpj->activity->unit_id,
                'activity_id' => $lockedLpj->activity_id,
                'assignee_id' => $data['to_user_id'] ?? null,
                'type' => 'DISPOSITION_RESPONSE',
                'title' => $data['subject'],
                'description' => $data['instructions'],
                'priority' => $data['priority'],
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => $data['due_at'] ?? null,
                'metadata' => ['disposition_id' => $disposition->id, 'to_role' => $data['to_role'] ?? null],
            ]);
            DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));

            $this->recordAuditEvent->handle($disposition, 'disposition.created', $user, null, DispositionStatus::Open->value, [
                'lpj_version_id' => $version->id,
                'recipient_id' => $data['to_user_id'] ?? null,
                'recipient_role' => $data['to_role'] ?? null,
            ]);

            return $disposition->load(['sender', 'recipient']);
        }, attempts: 3);
    }
}
