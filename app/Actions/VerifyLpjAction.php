<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Enums\GovernancePolicyType;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use App\Models\User;
use App\Services\GovernancePolicyRegistry;
use App\Services\LpjVerificationGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyLpjAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private CreateDispositionAction $createDisposition,
        private LpjVerificationGate $verificationGate,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data): Lpj
    {
        return DB::transaction(function () use ($lpj, $user, $data): Lpj {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            $stage = VerificationStage::from($data['stage']);
            $status = VerificationStatus::from($data['status']);
            $version = $lockedLpj->currentVersionRecord()->with(['verifications', 'findings', 'dispositions'])->firstOrFail();
            $this->verificationGate->assertCanRecord($lockedLpj, $version, $user, $stage, $status);

            $expectedChecklist = $this->verificationGate->checklistFor($version, $stage);
            $submittedChecklist = collect($data['checklist'] ?? [])->pluck('item')->all();
            if ($submittedChecklist !== $expectedChecklist) {
                throw ValidationException::withMessages(['checklist' => 'Checklist harus mengikuti template domain pada kebijakan verifikasi versi ini.']);
            }

            $verification = $version->verifications()->where('stage', $stage->value)->firstOrFail();
            $verification->update([
                'verifier_id' => $user->id,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'checklist' => $data['checklist'] ?? null,
                'evidence_ids' => $data['evidence_ids'] ?? null,
                'material_exception' => $data['material_exception'] ?? null,
                'verified_at' => now(),
            ]);

            $fromStatus = $lockedLpj->status->value;
            $toStatus = match ($status) {
                VerificationStatus::ClarificationRequired => LpjStatus::ClarificationRequired,
                VerificationStatus::Finding => LpjStatus::FindingOpen,
                VerificationStatus::Returned => LpjStatus::Returned,
                default => $lockedLpj->status,
            };

            $allPassed = $version->verifications()->where('status', '!=', VerificationStatus::Verified->value)->doesntExist();
            $hasOpenFindings = $version->findings()->whereIn('status', [FindingStatus::Open->value, FindingStatus::CapaSubmitted->value])->exists();
            if ($allPassed && ! $hasOpenFindings) {
                $toStatus = LpjStatus::ReadyForApproval;
            }

            $lockedLpj->update(['status' => $toStatus]);
            if ($status === VerificationStatus::ClarificationRequired) {
                $clarificationDays = (int) data_get(
                    $version->snapshot,
                    'verification.clarification_due_days',
                    $this->policyRegistry->get(GovernancePolicyType::Verification)['clarification_due_days'] ?? 3,
                );
                $this->createDisposition->handle($lockedLpj, $user, [
                    'to_user_id' => $version->submitted_by,
                    'subject' => 'Klarifikasi verifikasi '.$stage->value.' LPJ v'.$version->version,
                    'instructions' => $data['notes'],
                    'priority' => 'HIGH',
                    'due_at' => now()->addDays($clarificationDays),
                    'context' => LpjVerificationGate::clarificationContext($stage),
                ]);
            }

            $this->recordAuditEvent->handle($lockedLpj, 'lpj.verification_recorded', $user, $fromStatus, $toStatus->value, [
                'stage' => $stage->value,
                'result' => $status->value,
                'lpj_version' => $version->version,
                'policy_version' => data_get($version->snapshot, 'verification.policy_version'),
            ]);
            $this->synchronizeWorkflowTasks->handle($lockedLpj, $user);

            return $lockedLpj->fresh(['activity', 'currentVersionRecord.verifications', 'currentVersionRecord.findings', 'currentVersionRecord.dispositions']);
        });
    }
}
