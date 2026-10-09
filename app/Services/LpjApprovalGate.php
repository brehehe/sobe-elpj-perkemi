<?php

namespace App\Services;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\DispositionStatus;
use App\Enums\FindingStatus;
use App\Enums\LpjStatus;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\VerificationStatus;
use App\Models\ApprovalDelegation;
use App\Models\AuditEvent;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class LpjApprovalGate
{
    public const CLARIFICATION_CONTEXT_PREFIX = 'LPJ_APPROVAL_CLARIFICATION:';

    /** @return array<string, mixed> */
    public function evaluate(Lpj $lpj, ?User $actor = null, ?ApprovalLevel $level = null): array
    {
        $version = $lpj->currentVersionRecord;
        $level ??= $this->currentLevel($lpj, $version);

        if ($version === null) {
            return [
                'policy_version' => (string) config('lpj.approval_policy.version'),
                'level' => $level->value,
                'level_label' => $this->definition($level)['label'],
                'can_decide' => false,
                'can_approve' => false,
                'blockers' => ['Snapshot LPJ belum tersedia.'],
                'authority' => null,
                'checks' => [],
                'decision_pack' => null,
            ];
        }

        $this->loadDecisionRelations($lpj, $version);
        $authority = $actor === null ? null : $this->resolveAuthority($lpj, $actor, $level);
        $checks = $this->gateChecks($lpj, $version, $actor, $level, $authority);
        $decisionPack = $this->decisionPack($lpj, $version);
        $blockers = collect($checks)->where('passed', false)->pluck('message')->values()->all();

        return [
            'policy_version' => (string) config('lpj.approval_policy.version'),
            'level' => $level->value,
            'level_label' => $this->definition($level)['label'],
            'can_decide' => $actor !== null && ($authority['valid'] ?? false) && collect($checks)
                ->whereIn('key', ['status', 'version', 'integrity', 'conflict_of_interest'])
                ->every('passed'),
            'can_approve' => $actor !== null && ($authority['valid'] ?? false) && $blockers === [],
            'blockers' => $blockers,
            'authority' => $authority,
            'checks' => array_values($checks),
            'decision_pack' => $decisionPack,
        ];
    }

    public function hasAuthority(Lpj $lpj, User $user, ApprovalLevel $level): bool
    {
        return $this->resolveAuthority($lpj, $user, $level)['valid'];
    }

    /** @return array<string, mixed> */
    public function assertCanDecide(Lpj $lpj, LpjVersion $version, User $user, ApprovalLevel $level, ApprovalDecision $decision): array
    {
        $this->loadDecisionRelations($lpj, $version);
        $authority = $this->resolveAuthority($lpj, $user, $level);
        $checks = $this->gateChecks($lpj, $version, $user, $level, $authority);
        $requiredKeys = ['authority', 'status', 'version', 'integrity', 'conflict_of_interest'];

        if ($decision === ApprovalDecision::Approved) {
            $requiredKeys = array_column($checks, 'key');
        }

        $failed = collect($checks)->whereIn('key', $requiredKeys)->firstWhere('passed', false);
        if ($failed !== null) {
            throw ValidationException::withMessages([$failed['field'] => $failed['message']]);
        }

        $decisionPack = $this->decisionPack($lpj, $version);

        return [
            'authority' => $authority,
            'decision_pack' => $decisionPack,
            'decision_pack_checksum' => $this->checksum($decisionPack),
        ];
    }

    public static function clarificationContext(ApprovalLevel $level): string
    {
        return self::CLARIFICATION_CONTEXT_PREFIX.$level->value;
    }

    /** @return array<string, mixed> */
    private function resolveAuthority(Lpj $lpj, User $user, ApprovalLevel $level): array
    {
        $definition = $this->definition($level);
        $amount = (float) $lpj->total_amount;
        $unitMatches = $user->isGlobalAdministrator() || $definition['unit_scope'] === 'GLOBAL' || $user->unit_id === $lpj->activity->unit_id;
        $roleMatches = $user->hasAnyRole($definition['roles']);
        $permissionMatches = $user->can($definition['permission']);
        $withinDirectAmount = $definition['max_amount'] === null || $amount <= (float) $definition['max_amount'];
        $direct = $user->is_active && $lpj->activity->unit->is_active && $unitMatches && $roleMatches && $permissionMatches && $withinDirectAmount;

        $delegation = ApprovalDelegation::query()
            ->where('delegate_id', $user->id)
            ->where('level', $level->value)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(fn ($query) => $query->whereNull('unit_id')->orWhere('unit_id', $lpj->activity->unit_id))
            ->where(fn ($query) => $query->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->latest('valid_until')
            ->first();

        $source = $direct ? 'DIRECT' : ($delegation === null || ! $user->is_active || ! $lpj->activity->unit->is_active ? null : 'DELEGATION');

        return [
            'valid' => $source !== null,
            'source' => $source,
            'reference' => $direct ? 'DIRECT:'.$level->value : $delegation?->reference,
            'policy_version' => (string) config('lpj.approval_policy.version'),
            'level' => $level->value,
            'role' => $user->getRoleNames()->first(),
            'unit_scope' => $direct ? $definition['unit_scope'] : ($delegation?->unit_id === null ? 'GLOBAL' : 'ACTIVITY_UNIT'),
            'evaluated_amount' => number_format($amount, 2, '.', ''),
            'max_amount' => $direct ? $definition['max_amount'] : $delegation?->max_amount,
            'valid_from' => $delegation?->valid_from?->toIso8601String(),
            'valid_until' => $delegation?->valid_until?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $authority
     * @return list<array{key: string, label: string, passed: bool, field: string, message: string}>
     */
    private function gateChecks(Lpj $lpj, LpjVersion $version, ?User $actor, ApprovalLevel $level, ?array $authority): array
    {
        $expectedStatus = $level === ApprovalLevel::Approver ? LpjStatus::ReadyForApproval : LpjStatus::Approved;
        $openDispositionStatuses = [DispositionStatus::Open, DispositionStatus::Acknowledged, DispositionStatus::InProgress, DispositionStatus::EvidenceSubmitted, DispositionStatus::Reopened];
        $hasConflict = $actor !== null && (
            $version->submitted_by === $actor->id
            || $version->verifications->contains('verifier_id', $actor->id)
            || ($level === ApprovalLevel::Pb && $version->approvals->contains(
                fn ($approval) => $approval->level === ApprovalLevel::Approver && $approval->decided_by === $actor->id,
            ))
        );
        $snapshotChecksum = hash('sha256', json_encode($version->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $exceptions = collect(data_get($version->snapshot, 'transactions', []))
            ->flatMap(fn (array $transaction) => data_get($transaction, 'reconciliation.exceptions', []));
        $hasInvalidException = $exceptions->contains(function (array $exception): bool {
            $status = $exception['status'] ?? null;

            return in_array($status, ReconciliationExceptionStatus::BLOCKING_VALUES, true)
                || ($status === ReconciliationExceptionStatus::ApprovedException->value && ! ($exception['is_approved_exception'] ?? false));
        });
        $hasCurrentBlockingException = $lpj->activity->transactions->contains(fn ($transaction) => $transaction->reconciliation?->exceptions->contains(
            fn ($exception) => in_array($exception->status->value, ReconciliationExceptionStatus::BLOCKING_VALUES, true)
                || ($exception->status === ReconciliationExceptionStatus::ApprovedException && ! $exception->is_approved_exception),
        ) ?? false);
        $approverApproval = $version->approvals->first(fn ($approval) => $approval->level === ApprovalLevel::Approver && $approval->decision === ApprovalDecision::Approved);

        return [
            $this->check('authority', 'Kewenangan aktif', (bool) ($authority['valid'] ?? false), 'approver', 'Pengguna tidak memiliki jabatan atau delegasi aktif untuk level dan nilai LPJ ini.'),
            $this->check('status', 'Tahap keputusan', $lpj->status === $expectedStatus, 'status', 'LPJ belum memenuhi gerbang persetujuan ini.'),
            $this->check('version', 'Versi aktif', $lpj->current_version === $version->version, 'version', 'Versi yang akan diputuskan bukan versi LPJ yang sedang terbit.'),
            $this->check('integrity', 'Integritas snapshot', hash_equals($version->checksum, $snapshotChecksum), 'checksum', 'Checksum snapshot LPJ tidak valid.'),
            $this->check('conflict_of_interest', 'Pemisahan tugas', ! $hasConflict, 'approver', 'Pengaju, pemeriksa, atau approver sebelumnya tidak boleh mengambil keputusan pada level ini.'),
            $this->check('verifications', 'Semua verifikasi wajib', $version->verifications->isNotEmpty() && $version->verifications->every(fn ($verification) => $verification->status === VerificationStatus::Verified), 'verification', 'Seluruh verifikasi wajib harus berstatus VERIFIED.'),
            $this->check('findings', 'Temuan material selesai', ! $version->findings->contains(fn ($finding) => in_array($finding->status, [FindingStatus::Open, FindingStatus::CapaSubmitted], true)), 'finding', 'Temuan atau CAPA material masih terbuka.'),
            $this->check('dispositions', 'Disposisi selesai', ! $version->dispositions->contains(fn ($disposition) => in_array($disposition->status, $openDispositionStatuses, true)), 'disposition', 'Seluruh disposisi wajib ditutup sebelum persetujuan.'),
            $this->check('reconciliation', 'Rekonsiliasi dan exception', ! $hasInvalidException && ! $hasCurrentBlockingException && ! $lpj->activity->transactions->contains(fn ($transaction) => in_array($transaction->reconciliation_status->value, ['UNMATCHED', 'PENDING', 'EXCEPTION'], true)), 'reconciliation', 'Rekonsiliasi atau exception keuangan belum memenuhi kebijakan.'),
            $this->check('prior_approval', 'Approval berjenjang', $level === ApprovalLevel::Approver || ($approverApproval?->decision === ApprovalDecision::Approved && $approverApproval->lpj_checksum === $version->checksum), 'approval', 'PB hanya dapat memverifikasi approval APPROVED pada versi aktif yang sama.'),
        ];
    }

    /** @return array{key: string, label: string, passed: bool, field: string, message: string} */
    private function check(string $key, string $label, bool $passed, string $field, string $message): array
    {
        return compact('key', 'label', 'passed', 'field', 'message');
    }

    /** @return array<string, mixed> */
    private function decisionPack(Lpj $lpj, LpjVersion $version): array
    {
        $snapshot = $version->snapshot;
        $transactions = collect(data_get($snapshot, 'transactions', []));
        $actual = (float) data_get($snapshot, 'total_amount', 0);
        $budget = (float) data_get($snapshot, 'activity.budget_ceiling', 0);
        $exceptions = $transactions->flatMap(fn (array $transaction) => data_get($transaction, 'reconciliation.exceptions', []));
        $approvedExceptions = $exceptions->filter(fn (array $exception) => ($exception['status'] ?? null) === ReconciliationExceptionStatus::ApprovedException->value && ($exception['is_approved_exception'] ?? false));

        return [
            'schema_version' => 1,
            'lpj' => [
                'id' => $lpj->id,
                'version_id' => $version->id,
                'version' => $version->version,
                'checksum' => $version->checksum,
                'status' => $lpj->status->value,
                'submitted_at' => $version->submitted_at?->toIso8601String(),
            ],
            'activity' => [
                ...Arr::only(data_get($snapshot, 'activity', []), ['id', 'code', 'title', 'description', 'location', 'starts_at', 'ends_at']),
                'unit' => $lpj->activity->unit->only(['id', 'code', 'name']),
                'mandate' => Arr::only(data_get($snapshot, 'mandate', []), ['id', 'number', 'title']),
                'summary' => Arr::only(data_get($snapshot, 'narratives', []), ['executive_summary', 'implementation_narrative', 'outcome_narrative']),
            ],
            'financial' => [
                'currency' => (string) data_get($snapshot, 'budget.currency', 'IDR'),
                'budget' => number_format($budget, 2, '.', ''),
                'actual' => number_format($actual, 2, '.', ''),
                'variance' => number_format($budget - $actual, 2, '.', ''),
                'transaction_count' => $transactions->count(),
                'reconciled_count' => $transactions->filter(fn (array $transaction) => in_array($transaction['reconciliation_status'] ?? null, ['MATCHED', 'RESOLVED'], true))->count(),
            ],
            'verifications' => $version->verifications->map(fn ($verification) => [
                'stage' => $verification->stage->value,
                'status' => $verification->status->value,
                'verifier' => $verification->verifier?->only(['id', 'name']),
                'notes' => $verification->notes,
                'checklist' => $verification->checklist,
                'material_exception' => $verification->material_exception,
                'verified_at' => $verification->verified_at?->toIso8601String(),
            ])->values()->all(),
            'findings' => $version->findings->map(fn ($finding) => [
                ...$finding->only(['id', 'code', 'severity', 'status', 'title', 'risk', 'recommendation', 'resolved_at']),
                'corrective_actions' => $finding->correctiveActions->map->only(['id', 'action', 'status', 'review_notes', 'submitted_at', 'reviewed_at'])->values()->all(),
            ])->values()->all(),
            'approved_exceptions' => $approvedExceptions->values()->all(),
            'open_dispositions' => $version->dispositions
                ->reject(fn ($disposition) => $disposition->status === DispositionStatus::Closed)
                ->map->only(['id', 'subject', 'priority', 'status', 'context', 'due_at'])
                ->values()->all(),
            'audit_history' => AuditEvent::query()
                ->where('auditable_type', $lpj->getMorphClass())
                ->where('auditable_id', $lpj->id)
                ->orderByDesc('created_at')
                ->orderByDesc('event_id')
                ->limit(20)
                ->get(['event_id', 'correlation_id', 'request_id', 'object_version', 'event_type', 'source_channel', 'authority', 'payload_version', 'from_status', 'to_status', 'result', 'evidence_references', 'created_at'])
                ->map(fn (AuditEvent $event) => [
                    ...$event->only(['event_id', 'correlation_id', 'request_id', 'object_version', 'event_type', 'source_channel', 'authority', 'payload_version', 'from_status', 'to_status', 'result', 'evidence_references']),
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->all(),
            'source_references' => [
                'activity_id' => data_get($snapshot, 'activity.id'),
                'mandate_id' => data_get($snapshot, 'mandate.id'),
                'plan_id' => data_get($snapshot, 'plan.id'),
                'plan_version' => data_get($snapshot, 'plan.current_version'),
                'budget_id' => data_get($snapshot, 'budget.id'),
                'budget_version' => data_get($snapshot, 'budget.current_version'),
                'evidence' => collect(data_get($snapshot, 'evidence', []))->map(fn (array $evidence) => Arr::only($evidence, ['id', 'version', 'sha256']))->values()->all(),
            ],
        ];
    }

    /** @param array<string, mixed> $decisionPack */
    private function checksum(array $decisionPack): string
    {
        return hash('sha256', json_encode($decisionPack, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function currentLevel(Lpj $lpj, ?LpjVersion $version): ApprovalLevel
    {
        if (in_array($lpj->status, [LpjStatus::Approved, LpjStatus::PbVerified, LpjStatus::Archived], true)) {
            return ApprovalLevel::Pb;
        }

        if ($lpj->status === LpjStatus::ClarificationRequired && $version !== null) {
            return $version->approvals()->latest('decided_at')->value('level') === ApprovalLevel::Pb->value
                ? ApprovalLevel::Pb
                : ApprovalLevel::Approver;
        }

        return ApprovalLevel::Approver;
    }

    /** @return array{label: string, permission: string, roles: list<string>, unit_scope: string, max_amount: int|float|string|null} */
    private function definition(ApprovalLevel $level): array
    {
        /** @var array{label: string, permission: string, roles: list<string>, unit_scope: string, max_amount: int|float|string|null} $definition */
        $definition = config('lpj.approval_policy.levels.'.$level->value);

        return $definition;
    }

    private function loadDecisionRelations(Lpj $lpj, LpjVersion $version): void
    {
        $lpj->loadMissing(['activity.unit', 'activity.transactions.reconciliation.exceptions']);
        $version->loadMissing(['verifications.verifier', 'findings.correctiveActions', 'approvals', 'dispositions']);
    }
}
