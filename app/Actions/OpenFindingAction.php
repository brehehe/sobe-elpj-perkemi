<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Enums\LpjStatus;
use App\Enums\TaskStatus;
use App\Models\Finding;
use App\Models\Lpj;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenFindingAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data): Finding
    {
        return DB::transaction(function () use ($lpj, $user, $data): Finding {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            if (! in_array($lockedLpj->status, [LpjStatus::InVerification, LpjStatus::ClarificationRequired, LpjStatus::FindingOpen], true)) {
                throw ValidationException::withMessages(['finding' => 'Temuan hanya dapat dibuka saat LPJ berada pada tahap verifikasi.']);
            }

            $version = $lockedLpj->currentVersionRecord()->firstOrFail();
            if ($version->submitted_by === $user->id) {
                throw ValidationException::withMessages(['finding' => 'Pengaju tidak boleh membuat temuan pada LPJ sendiri.']);
            }

            $finding = Finding::create([
                ...$data,
                'lpj_version_id' => $version->id,
                'created_by' => $user->id,
                'code' => 'TM-'.now()->format('Ym').'-'.Str::upper(Str::random(6)),
                'status' => FindingStatus::Open,
            ]);

            $fromStatus = $lockedLpj->status->value;
            $lockedLpj->update(['status' => LpjStatus::FindingOpen]);
            $task = SmartTask::create([
                'unit_id' => $lockedLpj->activity->unit_id,
                'activity_id' => $lockedLpj->activity_id,
                'assignee_id' => $data['owner_id'] ?? $lockedLpj->owner_id,
                'type' => 'FINDING_RESPONSE',
                'title' => 'Tindak lanjuti '.$finding->code,
                'description' => $finding->title,
                'priority' => $finding->severity,
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => $finding->due_at?->endOfDay(),
                'metadata' => ['finding_id' => $finding->id],
            ]);

            DB::afterCommit(function () use ($task): void {
                $task->assignee?->notify(new WorkflowTaskAssigned($task));
            });

            $this->recordAuditEvent->handle($finding, 'finding.opened', $user, $fromStatus, LpjStatus::FindingOpen->value);

            return $finding;
        });
    }
}
