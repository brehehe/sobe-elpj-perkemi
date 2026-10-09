<?php

namespace App\Actions;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LpjStatus;
use App\Models\Approval;
use App\Models\Lpj;
use App\Models\User;
use App\Services\LpjApprovalGate;
use Illuminate\Support\Facades\DB;

class ApproveLpjAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private CreateDispositionAction $createDisposition,
        private LpjApprovalGate $approvalGate,
        private IssueVerifiedPackAction $issueVerifiedPack,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data): Approval
    {
        return DB::transaction(function () use ($lpj, $user, $data): Approval {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            $version = $lockedLpj->currentVersionRecord()->with(['verifications', 'dispositions'])->firstOrFail();
            $level = ApprovalLevel::from($data['level']);
            $decision = ApprovalDecision::from($data['decision']);
            $evaluation = $this->approvalGate->assertCanDecide($lockedLpj, $version, $user, $level, $decision);
            $decidedAt = now();

            $approval = Approval::create([
                'lpj_version_id' => $version->id,
                'decided_by' => $user->id,
                'level' => $level,
                'decision' => $decision,
                'notes' => $data['notes'] ?? null,
                'authority_policy_version' => config('lpj.approval_policy.version'),
                'authority_reference' => $evaluation['authority']['reference'],
                'authority_snapshot' => $evaluation['authority'],
                'decision_pack' => $evaluation['decision_pack'],
                'decision_pack_checksum' => $evaluation['decision_pack_checksum'],
                'lpj_checksum' => $version->checksum,
                'signature_hash' => hash('sha256', implode('|', [
                    $version->checksum,
                    $evaluation['decision_pack_checksum'],
                    config('lpj.approval_policy.version'),
                    $user->id,
                    $level->value,
                    $decision->value,
                    $decidedAt->toIso8601String(),
                ])),
                'decided_at' => $decidedAt,
            ]);

            $verifiedPack = null;
            if ($level === ApprovalLevel::Pb && $decision === ApprovalDecision::Approved) {
                $verifiedPack = $this->issueVerifiedPack->handle($version, $approval, $user);
            }

            $fromStatus = $lockedLpj->status->value;
            $toStatus = match (true) {
                $decision === ApprovalDecision::Returned => LpjStatus::Returned,
                $decision === ApprovalDecision::ClarificationRequired => LpjStatus::ClarificationRequired,
                $decision === ApprovalDecision::Rejected => LpjStatus::Rejected,
                $level === ApprovalLevel::Pb => LpjStatus::PbVerified,
                default => LpjStatus::Approved,
            };
            $updates = ['status' => $toStatus];
            if ($toStatus === LpjStatus::Approved) {
                $updates['approved_at'] = now();
            }
            if ($toStatus === LpjStatus::PbVerified) {
                $updates['pb_verified_at'] = now();
            }
            if (in_array($toStatus, [LpjStatus::Returned, LpjStatus::Rejected], true)) {
                $updates['approved_at'] = null;
                $updates['pb_verified_at'] = null;
            }
            $lockedLpj->update($updates);
            if ($decision === ApprovalDecision::ClarificationRequired) {
                $this->createDisposition->handle($lockedLpj, $user, [
                    'to_user_id' => $version->submitted_by,
                    'subject' => 'Klarifikasi '.$level->value.' LPJ v'.$version->version,
                    'instructions' => $data['notes'],
                    'priority' => 'HIGH',
                    'due_at' => now()->addDays((int) config('lpj.approval_policy.clarification_due_days', 3)),
                    'context' => LpjApprovalGate::clarificationContext($level),
                ]);
            }
            $this->recordAuditEvent->handle($lockedLpj, 'lpj.approval_recorded', $user, $fromStatus, $toStatus->value, [
                'level' => $level->value,
                'decision' => $decision->value,
                'authority_policy_version' => $approval->authority_policy_version,
                'authority_reference' => $approval->authority_reference,
                'decision_pack_checksum' => $approval->decision_pack_checksum,
                'signature_hash' => $approval->signature_hash,
                'verification_id' => $verifiedPack?->id,
                'verification_number' => $verifiedPack?->verification_number,
                'verified_pack_checksum' => $verifiedPack?->checksum,
            ]);

            if ($verifiedPack) {
                $this->recordAuditEvent->handle($verifiedPack, 'lpj.verified_pack_issued', $user, payload: [
                    'verification_number' => $verifiedPack->verification_number,
                    'policy_version' => $verifiedPack->policy_version,
                    'checksum' => $verifiedPack->checksum,
                    'approval_id' => $approval->id,
                ]);
            }
            $this->synchronizeWorkflowTasks->handle($lockedLpj, $user);

            return $approval;
        }, attempts: 3);
    }
}
