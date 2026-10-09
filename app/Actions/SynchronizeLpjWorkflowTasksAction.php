<?php

namespace App\Actions;

use App\Enums\GovernancePolicyType;
use App\Enums\LpjStatus;
use App\Enums\TaskStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use App\Models\SmartTask;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\WorkflowTaskAssigned;
use App\Services\GovernancePolicyRegistry;
use App\Services\SmartTaskPresentationService;
use Illuminate\Support\Facades\DB;

class SynchronizeLpjWorkflowTasksAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private SmartTaskPresentationService $presenter,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    public function handle(Lpj $lpj, ?User $actor = null): ?SmartTask
    {
        return DB::transaction(function () use ($lpj, $actor): ?SmartTask {
            $lockedLpj = Lpj::query()
                ->with(['activity', 'owner', 'currentVersionRecord.verifications'])
                ->lockForUpdate()
                ->findOrFail($lpj->id);
            $version = $lockedLpj->currentVersionRecord;

            if ($version === null) {
                return null;
            }

            $specification = $this->specification($lockedLpj);
            $desiredKey = $specification === null
                ? null
                : $this->deduplicationKey($lockedLpj, $version->version, $specification['type']);

            $this->completeObsoleteTasks($lockedLpj, $desiredKey, $actor);
            $this->completeComposerTask($lockedLpj, $actor);

            if ($specification === null) {
                return null;
            }

            $definition = $this->presenter->definition($specification['type']);
            $assignee = $this->resolveAssignee(
                $lockedLpj,
                $specification['permission'],
                $specification['global'],
                $specification['type'],
            );
            $dueAt = now()->addDays($specification['due_days'])->endOfDay();
            $task = SmartTask::query()->firstOrNew(['deduplication_key' => $desiredKey]);
            $wasActive = $task->exists && in_array($task->status, [TaskStatus::Open, TaskStatus::InProgress], true);
            $fromStatus = $task->exists
                ? ($task->status instanceof TaskStatus ? $task->status->value : (string) $task->status)
                : null;

            $task->fill([
                'unit_id' => $lockedLpj->activity->unit_id,
                'activity_id' => $lockedLpj->activity_id,
                'subject_type' => 'LPJ',
                'subject_id' => $lockedLpj->id,
                'assignee_id' => $assignee?->id,
                'recipient_permission' => $specification['permission'],
                'type' => $specification['type'],
                'title' => $specification['title'],
                'description' => $specification['description'],
                'completion_condition' => $definition['completion'],
                'priority' => $specification['priority'],
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => $wasActive ? $task->due_at : $dueAt,
                'escalation_path' => $definition['escalation'],
                'escalation_at' => $wasActive
                    ? $task->escalation_at
                    : $dueAt->copy()->subHours((int) ($this->workflowPolicy()['escalation_lead_hours'] ?? 12)),
                'started_at' => null,
                'completed_at' => null,
                'metadata' => [
                    'lpj_id' => $lockedLpj->id,
                    'lpj_version_id' => $version->id,
                    'lpj_version' => $version->version,
                    'verification_stage' => $specification['stage']?->value,
                    'recipient_permission' => $specification['permission'],
                    'recipient_label' => $specification['recipient_label'],
                    'workflow_policy_version' => $wasActive
                        ? data_get($task->metadata, 'workflow_policy_version', $this->workflowPolicy()['version'])
                        : $this->workflowPolicy()['version'],
                    'action_url' => route('lpjs.show', $lockedLpj, false).$specification['anchor'],
                ],
            ]);
            $task->save();

            if (! $wasActive) {
                $this->recordAuditEvent->handle($task, 'workflow.task_activated', $actor, $fromStatus, TaskStatus::Open->value, [
                    'lpj_id' => $lockedLpj->id,
                    'lpj_version' => $version->version,
                    'recipient_permission' => $specification['permission'],
                    'workflow_policy_version' => $this->workflowPolicy()['version'],
                ]);
                DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            }

            return $task->fresh(['unit', 'activity', 'assignee']);
        }, attempts: 3);
    }

    /**
     * @return array{type: string, permission: string, global: bool, title: string, description: string, priority: string, due_days: int, recipient_label: string, anchor: string, stage: VerificationStage|null}|null
     */
    private function specification(Lpj $lpj): ?array
    {
        $activityLabel = $lpj->activity->code.' · '.$lpj->activity->title;

        if ($lpj->status === LpjStatus::InVerification) {
            $verification = $this->nextVerification($lpj);
            if ($verification === null) {
                return null;
            }

            $stage = $verification->stage;
            $type = match ($stage) {
                VerificationStage::Administration => 'VERIFY_LPJ_ADMINISTRATION',
                VerificationStage::Finance => 'VERIFY_LPJ_FINANCE',
                VerificationStage::Substantive => 'VERIFY_LPJ_SUBSTANTIVE',
                VerificationStage::System => null,
            };
            if ($type === null) {
                return null;
            }

            $permission = 'lpjs.verify.'.str($stage->value)->lower()->toString();
            $label = data_get(
                $lpj->currentVersionRecord->snapshot,
                "verification.stages.{$stage->value}.label",
                data_get($this->policyRegistry->get(GovernancePolicyType::Verification), "stages.{$stage->value}.label", $stage->value),
            );

            return [
                'type' => $type,
                'permission' => $permission,
                'global' => false,
                'title' => 'Verifikasi '.$label.' '.$lpj->activity->code,
                'description' => 'Periksa checklist, evidence, dan snapshot LPJ v'.$lpj->current_version.' untuk '.$activityLabel.'.',
                'priority' => 'HIGH',
                'due_days' => (int) ($this->workflowPolicy()['verification_due_days'] ?? 3),
                'recipient_label' => 'Verifikator '.$label,
                'anchor' => '#lpj-verification',
                'stage' => $stage,
            ];
        }

        return match ($lpj->status) {
            LpjStatus::Returned => [
                'type' => 'REVISE_LPJ',
                'permission' => 'lpjs.submit',
                'global' => false,
                'title' => 'Perbaiki LPJ '.$lpj->activity->code,
                'description' => 'Tinjau alasan pengembalian, perbaiki data sumber atau narasi, lalu ajukan snapshot versi berikutnya.',
                'priority' => 'HIGH',
                'due_days' => (int) ($this->workflowPolicy()['revision_due_days'] ?? 3),
                'recipient_label' => 'Activity Owner',
                'anchor' => '#lpj-composer',
                'stage' => null,
            ],
            LpjStatus::ReadyForApproval => [
                'type' => 'APPROVE_LPJ',
                'permission' => 'lpjs.approve',
                'global' => false,
                'title' => 'Putuskan LPJ '.$lpj->activity->code,
                'description' => 'Periksa decision pack, kewenangan, hasil verifikasi, exception, dan conflict of interest.',
                'priority' => 'HIGH',
                'due_days' => (int) ($this->workflowPolicy()['approval_due_days'] ?? 2),
                'recipient_label' => 'Approver unit',
                'anchor' => '#lpj-approval',
                'stage' => null,
            ],
            LpjStatus::Approved => [
                'type' => 'PB_VERIFY_LPJ',
                'permission' => 'lpjs.pb_verify',
                'global' => true,
                'title' => 'Verifikasi PB '.$lpj->activity->code,
                'description' => 'Pastikan versi final sama dengan decision pack sebelum menerbitkan Verified Pack.',
                'priority' => 'CRITICAL',
                'due_days' => (int) ($this->workflowPolicy()['pb_verification_due_days'] ?? 2),
                'recipient_label' => 'Verifikator PB',
                'anchor' => '#lpj-approval',
                'stage' => null,
            ],
            LpjStatus::PbVerified => [
                'type' => 'ARCHIVE_LPJ',
                'permission' => 'lpjs.archive',
                'global' => false,
                'title' => 'Arsipkan LPJ '.$lpj->activity->code,
                'description' => 'Validasi integritas Verified Pack, manifest evidence, dan kebijakan retensi sebelum pengarsipan.',
                'priority' => 'HIGH',
                'due_days' => (int) ($this->workflowPolicy()['archive_due_days'] ?? 2),
                'recipient_label' => 'Administrator arsip',
                'anchor' => '#lpj-approval',
                'stage' => null,
            ],
            default => null,
        };
    }

    private function nextVerification(Lpj $lpj): ?Verification
    {
        $verifications = $lpj->currentVersionRecord->verifications->keyBy(fn ($verification): string => $verification->stage->value);

        $stages = data_get(
            $lpj->currentVersionRecord->snapshot,
            'verification.stages',
            $this->policyRegistry->get(GovernancePolicyType::Verification)['stages'] ?? [],
        );

        foreach (array_keys($stages) as $stageValue) {
            $stage = VerificationStage::from($stageValue);
            if ($stage === VerificationStage::System) {
                continue;
            }

            $verification = $verifications->get($stage->value);
            if ($verification !== null && $verification->status !== VerificationStatus::Verified) {
                return $verification;
            }
        }

        return null;
    }

    private function resolveAssignee(Lpj $lpj, string $permission, bool $global, string $type): ?User
    {
        if ($type === 'REVISE_LPJ') {
            return $lpj->owner;
        }

        return User::permission($permission)
            ->where('is_active', true)
            ->when(! $global, fn ($query) => $query->where('unit_id', $lpj->activity->unit_id))
            ->when($type !== 'ARCHIVE_LPJ', fn ($query) => $query->whereKeyNot($lpj->currentVersionRecord->submitted_by))
            ->orderBy('name')
            ->first();
    }

    private function completeObsoleteTasks(Lpj $lpj, ?string $desiredKey, ?User $actor): void
    {
        SmartTask::query()
            ->where('metadata->lpj_id', $lpj->id)
            ->whereIn('type', $this->presenter->lpjWorkflowTypes())
            ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            ->when($desiredKey !== null, fn ($query) => $query->where('deduplication_key', '!=', $desiredKey))
            ->get()
            ->each(function (SmartTask $task) use ($actor, $lpj): void {
                $fromStatus = $task->status->value;
                $task->update(['status' => TaskStatus::Done, 'completed_at' => now()]);
                $this->recordAuditEvent->handle($task, 'workflow.task_completed', $actor, $fromStatus, TaskStatus::Done->value, [
                    'lpj_id' => $lpj->id,
                    'completion' => 'Workflow LPJ bergerak ke gate berikutnya.',
                ]);
            });
    }

    private function completeComposerTask(Lpj $lpj, ?User $actor): void
    {
        SmartTask::query()
            ->where('activity_id', $lpj->activity_id)
            ->where('type', 'COMPLETE_LPJ')
            ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            ->get()
            ->each(function (SmartTask $task) use ($actor, $lpj): void {
                $fromStatus = $task->status->value;
                $task->update(['status' => TaskStatus::Done, 'completed_at' => now()]);
                $this->recordAuditEvent->handle($task, 'workflow.task_completed', $actor, $fromStatus, TaskStatus::Done->value, [
                    'lpj_id' => $lpj->id,
                    'completion' => 'Snapshot LPJ immutable berhasil diajukan.',
                ]);
            });
    }

    private function deduplicationKey(Lpj $lpj, int $version, string $type): string
    {
        return implode(':', ['lpj', $lpj->id, 'v'.$version, $type]);
    }

    /** @return array<string, mixed> */
    private function workflowPolicy(): array
    {
        return $this->policyRegistry->get(GovernancePolicyType::Workflow);
    }
}
