<?php

namespace App\Services;

use App\Enums\DispositionStatus;
use App\Enums\GovernancePolicyType;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LpjVerificationGate
{
    public const CLARIFICATION_CONTEXT_PREFIX = 'LPJ_VERIFICATION_CLARIFICATION:';

    public function __construct(private GovernancePolicyRegistry $policyRegistry) {}

    /**
     * @return array{
     *     policy_version: string,
     *     completed: int,
     *     total: int,
     *     percentage: int,
     *     next_stage: string|null,
     *     next_stage_label: string|null,
     *     can_verify_next_stage: bool,
     *     blocked_reason: string|null,
     *     stages: list<array<string, mixed>>
     * }
     */
    public function evaluate(Lpj $lpj, ?User $actor = null): array
    {
        $version = $lpj->currentVersionRecord;
        if ($version === null) {
            $currentPolicy = $this->policyRegistry->get(GovernancePolicyType::Verification);

            return [
                'policy_version' => (string) $currentPolicy['version'],
                'completed' => 0,
                'total' => 0,
                'percentage' => 0,
                'next_stage' => null,
                'next_stage_label' => null,
                'can_verify_next_stage' => false,
                'blocked_reason' => 'Snapshot LPJ belum tersedia.',
                'stages' => [],
            ];
        }

        $version->loadMissing(['verifications.verifier', 'findings', 'dispositions']);
        $policy = $this->policyFor($version);
        $verifications = $version->verifications->keyBy(fn ($verification): string => $verification->stage->value);
        $nextStage = $this->nextStage($version, $policy['stages']);
        $nextStageLabel = $nextStage === null ? null : $policy['stages'][$nextStage]['label'];
        $requiredStages = array_map(VerificationStage::from(...), array_keys($policy['stages']));
        $completed = $version->verifications
            ->where('status', VerificationStatus::Verified)
            ->whereIn('stage', $requiredStages)
            ->count();
        $blockedReason = $this->blockedReason($lpj, $version, $actor, $nextStage, $policy['stages']);

        $stages = collect($policy['stages'])->map(function (array $definition, string $stage) use ($verifications, $nextStage, $actor, $blockedReason): array {
            $verification = $verifications->get($stage);
            $isComplete = $verification?->status === VerificationStatus::Verified;
            $isActive = $stage === $nextStage;

            return [
                'stage' => $stage,
                'label' => $definition['label'],
                'status' => $verification?->status->value ?? VerificationStatus::Pending->value,
                'state' => $isComplete ? 'COMPLETED' : ($isActive ? 'ACTIVE' : 'LOCKED'),
                'checklist' => $definition['checklist'],
                'verifier' => $verification?->verifier?->only(['id', 'name']),
                'verified_at' => $verification?->verified_at?->toIso8601String(),
                'can_verify' => $isActive && $actor !== null && $blockedReason === null,
            ];
        })->values()->all();

        $total = count($policy['stages']);

        return [
            'policy_version' => $policy['version'],
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'next_stage' => $nextStage,
            'next_stage_label' => $nextStageLabel,
            'can_verify_next_stage' => $nextStage !== null && $blockedReason === null,
            'blocked_reason' => $blockedReason,
            'stages' => $stages,
        ];
    }

    public function assertCanRecord(
        Lpj $lpj,
        LpjVersion $version,
        User $user,
        VerificationStage $stage,
        VerificationStatus $decision,
    ): void {
        $version->loadMissing(['verifications', 'findings', 'dispositions']);
        $policy = $this->policyFor($version);
        $nextStage = $this->nextStage($version, $policy['stages']);

        if ($stage === VerificationStage::System) {
            throw ValidationException::withMessages(['stage' => 'Tahap system hanya dapat dicatat oleh pre-check otomatis.']);
        }

        if (! array_key_exists($stage->value, $policy['stages'])) {
            throw ValidationException::withMessages(['stage' => 'Tahap verifikasi tidak termasuk dalam kebijakan versi ini.']);
        }

        if ($nextStage !== $stage->value) {
            $requiredLabel = $nextStage === null ? 'seluruh tahap telah selesai' : $policy['stages'][$nextStage]['label'];
            throw ValidationException::withMessages(['stage' => "Urutan verifikasi wajib dipenuhi. Tahap berikutnya: {$requiredLabel}."]);
        }

        if (! $user->can('lpjs.verify.'.strtolower($stage->value))) {
            throw ValidationException::withMessages(['stage' => 'Anda tidak berwenang untuk tahap verifikasi ini.']);
        }

        if ($version->submitted_by === $user->id) {
            throw ValidationException::withMessages(['verifier' => 'Pembuat/pengaju tidak boleh memverifikasi LPJ yang sama.']);
        }

        $verification = $version->verifications->firstWhere('stage', $stage);
        if ($verification === null || in_array($verification->status, [VerificationStatus::Verified, VerificationStatus::Returned], true)) {
            throw ValidationException::withMessages(['stage' => 'Keputusan tahap ini sudah final dan tidak dapat ditimpa.']);
        }

        $hasOpenClarification = $version->dispositions
            ->where('context', self::clarificationContext($stage))
            ->contains(fn ($disposition): bool => $disposition->status !== DispositionStatus::Closed);
        if ($hasOpenClarification) {
            throw ValidationException::withMessages(['stage' => 'Klarifikasi tahap ini harus ditutup oleh pemeriksa sebelum verifikasi dilanjutkan.']);
        }

        $hasOpenFindings = $version->findings->contains(fn ($finding): bool => in_array($finding->status->value, ['OPEN', 'CAPA_SUBMITTED'], true));
        if ($decision === VerificationStatus::Finding && ! $hasOpenFindings) {
            throw ValidationException::withMessages(['status' => 'Buat temuan dan tetapkan CAPA sebelum mencatat keputusan FINDING.']);
        }
        if ($decision === VerificationStatus::Verified && $hasOpenFindings) {
            throw ValidationException::withMessages(['status' => 'Tahap belum dapat diverifikasi selama temuan atau CAPA masih terbuka.']);
        }

        if (! in_array($lpj->status, [LpjStatus::InVerification, LpjStatus::ClarificationRequired, LpjStatus::FindingOpen], true)) {
            throw ValidationException::withMessages(['status' => 'LPJ tidak sedang berada pada tahap verifikasi.']);
        }
    }

    /** @return list<string> */
    public function checklistFor(LpjVersion $version, VerificationStage $stage): array
    {
        return $this->policyFor($version)['stages'][$stage->value]['checklist'] ?? [];
    }

    public static function clarificationContext(VerificationStage $stage): string
    {
        return self::CLARIFICATION_CONTEXT_PREFIX.$stage->value;
    }

    /**
     * @param  array<string, array{label: string, checklist: list<string>}>  $stages
     */
    private function nextStage(LpjVersion $version, array $stages): ?string
    {
        $verifications = $version->verifications->keyBy(fn ($verification): string => $verification->stage->value);

        foreach (array_keys($stages) as $stage) {
            if ($verifications->get($stage)?->status !== VerificationStatus::Verified) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{label: string, checklist: list<string>}>  $stages
     */
    private function blockedReason(Lpj $lpj, LpjVersion $version, ?User $actor, ?string $nextStage, array $stages): ?string
    {
        if ($nextStage === null) {
            return 'Seluruh gate verifikasi telah selesai.';
        }

        if ($nextStage === VerificationStage::System->value) {
            return 'Menunggu pemeriksaan sistem otomatis.';
        }

        if (! in_array($lpj->status, [LpjStatus::InVerification, LpjStatus::ClarificationRequired, LpjStatus::FindingOpen], true)) {
            return 'Status LPJ tidak membuka aksi verifikasi.';
        }

        $stage = VerificationStage::from($nextStage);
        $verification = $version->verifications->firstWhere('stage', $stage);
        $hasOpenClarification = $version->dispositions
            ->where('context', self::clarificationContext($stage))
            ->contains(fn ($disposition): bool => $disposition->status !== DispositionStatus::Closed);
        if ($hasOpenClarification) {
            return 'Menunggu jawaban klarifikasi diperiksa dan ditutup.';
        }

        $hasOpenFindings = $version->findings->contains(fn ($finding): bool => in_array($finding->status->value, ['OPEN', 'CAPA_SUBMITTED'], true));
        if ($verification?->status === VerificationStatus::Finding && $hasOpenFindings) {
            return 'Menunggu CAPA atas temuan diverifikasi.';
        }

        if ($actor === null || ! $actor->can('lpjs.verify.'.strtolower($nextStage))) {
            return 'Menunggu verifikator '.$stages[$nextStage]['label'].' yang berwenang.';
        }

        if ($version->submitted_by === $actor->id) {
            return 'Pengaju LPJ tidak dapat memverifikasi pengajuannya sendiri.';
        }

        return null;
    }

    /**
     * @return array{version: string, stages: array<string, array{label: string, checklist: list<string>}>}
     */
    private function policyFor(LpjVersion $version): array
    {
        $snapshotPolicy = data_get($version->snapshot, 'verification');
        $configuredPolicy = $this->policyRegistry->get(GovernancePolicyType::Verification);
        $configuredStages = $configuredPolicy['stages'] ?? [];
        $stages = is_array($snapshotPolicy) && is_array($snapshotPolicy['stages'] ?? null)
            ? $snapshotPolicy['stages']
            : $configuredStages;

        return [
            'version' => is_array($snapshotPolicy)
                ? (string) ($snapshotPolicy['policy_version'] ?? $configuredPolicy['version'])
                : (string) $configuredPolicy['version'],
            'stages' => $stages,
        ];
    }
}
