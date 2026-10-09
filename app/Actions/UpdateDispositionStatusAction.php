<?php

namespace App\Actions;

use App\Enums\ApprovalLevel;
use App\Enums\DispositionStatus;
use App\Enums\LpjStatus;
use App\Enums\TaskStatus;
use App\Models\Disposition;
use App\Models\Lpj;
use App\Models\SmartTask;
use App\Models\User;
use App\Services\LpjApprovalGate;
use App\Services\LpjVerificationGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDispositionStatusAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Disposition $disposition, User $user, array $data): Disposition
    {
        return DB::transaction(function () use ($disposition, $user, $data): Disposition {
            $lockedDisposition = Disposition::query()->lockForUpdate()->findOrFail($disposition->id);
            $toStatus = DispositionStatus::from($data['status']);
            $isRecipient = $user->can('dispositions.respond')
                && ($lockedDisposition->to_user_id === null || $lockedDisposition->to_user_id === $user->id);
            $isReviewer = $user->can('dispositions.manage')
                && $lockedDisposition->to_user_id !== $user->id;

            $allowed = match (true) {
                $isRecipient && $lockedDisposition->status === DispositionStatus::Open => [DispositionStatus::Acknowledged],
                $isRecipient && in_array($lockedDisposition->status, [DispositionStatus::Acknowledged, DispositionStatus::Reopened], true) => [DispositionStatus::InProgress],
                $isRecipient && $lockedDisposition->status === DispositionStatus::InProgress => [DispositionStatus::EvidenceSubmitted],
                $isReviewer && $lockedDisposition->status === DispositionStatus::EvidenceSubmitted => [DispositionStatus::Closed, DispositionStatus::InProgress],
                $isReviewer && $lockedDisposition->status === DispositionStatus::Closed => [DispositionStatus::Reopened],
                default => [],
            };
            if (! in_array($toStatus, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Transisi status disposisi tidak valid.']);
            }
            if (in_array($toStatus, [DispositionStatus::Closed, DispositionStatus::Reopened], true) && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'Catatan pemeriksa wajib diisi untuk keputusan ini.']);
            }
            if ($lockedDisposition->status === DispositionStatus::EvidenceSubmitted && $toStatus === DispositionStatus::InProgress && blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => 'Catatan kekurangan bukti wajib diisi.']);
            }

            $fromStatus = $lockedDisposition->status->value;
            $updates = ['status' => $toStatus];
            if ($toStatus === DispositionStatus::Acknowledged) {
                $updates['acknowledged_at'] = now();
            }
            if ($toStatus === DispositionStatus::EvidenceSubmitted) {
                $updates['evidence_ids'] = $data['evidence_ids'];
                $updates['response_notes'] = $data['notes'];
            }
            if (in_array($toStatus, [DispositionStatus::Closed, DispositionStatus::Reopened], true)
                || ($lockedDisposition->status === DispositionStatus::EvidenceSubmitted && $toStatus === DispositionStatus::InProgress)) {
                $updates['reviewed_by'] = $user->id;
                $updates['review_notes'] = $data['notes'];
                $updates['reviewed_at'] = now();
            }
            if ($toStatus === DispositionStatus::Closed) {
                $updates['completed_at'] = now();
            }
            if ($toStatus === DispositionStatus::Reopened) {
                $updates['completed_at'] = null;
            }
            $lockedDisposition->update($updates);
            $lpjToSynchronize = null;

            if (str_starts_with((string) $lockedDisposition->context, LpjVerificationGate::CLARIFICATION_CONTEXT_PREFIX)) {
                $version = $lockedDisposition->lpjVersion;
                $lpj = $version->lpj()->lockForUpdate()->firstOrFail();
                if ($lpj->current_version === $version->version) {
                    $fromLpjStatus = $lpj->status;
                    $hasOpenClarification = $version->dispositions()
                        ->whereLike('context', LpjVerificationGate::CLARIFICATION_CONTEXT_PREFIX.'%')
                        ->where('status', '!=', DispositionStatus::Closed->value)
                        ->exists();

                    if ($toStatus === DispositionStatus::Closed && ! $hasOpenClarification && $lpj->status === LpjStatus::ClarificationRequired) {
                        $lpj->update(['status' => LpjStatus::InVerification]);
                    }
                    if ($toStatus === DispositionStatus::Reopened && $lpj->status === LpjStatus::InVerification) {
                        $lpj->update(['status' => LpjStatus::ClarificationRequired]);
                    }

                    if ($lpj->status !== $fromLpjStatus) {
                        $lpjToSynchronize = $lpj;
                        $this->recordAuditEvent->handle(
                            $lpj,
                            'lpj.clarification_status_changed',
                            $user,
                            $fromLpjStatus->value,
                            $lpj->status->value,
                            ['disposition_id' => $lockedDisposition->id],
                        );
                    }
                }
            }

            if (str_starts_with((string) $lockedDisposition->context, LpjApprovalGate::CLARIFICATION_CONTEXT_PREFIX)) {
                $version = $lockedDisposition->lpjVersion;
                $lpj = $version->lpj()->lockForUpdate()->firstOrFail();
                if ($lpj->current_version === $version->version) {
                    $fromLpjStatus = $lpj->status;
                    $levelValue = str($lockedDisposition->context)->after(LpjApprovalGate::CLARIFICATION_CONTEXT_PREFIX)->toString();
                    $level = ApprovalLevel::tryFrom($levelValue) ?? ApprovalLevel::Approver;
                    $resumedStatus = $level === ApprovalLevel::Pb ? LpjStatus::Approved : LpjStatus::ReadyForApproval;
                    $hasOpenClarification = $version->dispositions()
                        ->where('context', LpjApprovalGate::clarificationContext($level))
                        ->where('status', '!=', DispositionStatus::Closed->value)
                        ->exists();

                    if ($toStatus === DispositionStatus::Closed && ! $hasOpenClarification && $lpj->status === LpjStatus::ClarificationRequired) {
                        $lpj->update(['status' => $resumedStatus]);
                    }
                    if ($toStatus === DispositionStatus::Reopened && $lpj->status === $resumedStatus) {
                        $lpj->update(['status' => LpjStatus::ClarificationRequired]);
                    }

                    if ($lpj->status !== $fromLpjStatus) {
                        $lpjToSynchronize = $lpj;
                        $this->recordAuditEvent->handle(
                            $lpj,
                            'lpj.approval_clarification_status_changed',
                            $user,
                            $fromLpjStatus->value,
                            $lpj->status->value,
                            ['disposition_id' => $lockedDisposition->id, 'level' => $level->value],
                        );
                    }
                }
            }

            if ($lpjToSynchronize instanceof Lpj) {
                $this->synchronizeWorkflowTasks->handle($lpjToSynchronize, $user);
            }

            $taskStatus = match ($toStatus) {
                DispositionStatus::Closed => TaskStatus::Done,
                DispositionStatus::Reopened => TaskStatus::Open,
                default => TaskStatus::InProgress,
            };
            SmartTask::query()
                ->where('metadata->disposition_id', $lockedDisposition->id)
                ->update(['status' => $taskStatus]);

            $this->recordAuditEvent->handle($lockedDisposition, 'disposition.status_changed', $user, $fromStatus, $toStatus->value, [
                'evidence_ids' => $data['evidence_ids'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedDisposition->fresh(['sender', 'recipient', 'reviewer']);
        }, attempts: 3);
    }
}
