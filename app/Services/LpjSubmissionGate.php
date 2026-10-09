<?php

namespace App\Services;

use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityStatus;
use App\Enums\BudgetStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Enums\FindingStatus;
use App\Enums\GovernancePolicyType;
use App\Enums\PlanStatus;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Lpj;
use Illuminate\Support\Collection;

class LpjSubmissionGate
{
    public function __construct(private GovernancePolicyRegistry $policyRegistry) {}

    /** @var array<string, string> */
    public const NARRATIVE_FIELDS = [
        'executive_summary' => 'Ringkasan eksekutif',
        'implementation_narrative' => 'Narasi pelaksanaan',
        'outcome_narrative' => 'Narasi hasil dan outcome',
        'financial_narrative' => 'Narasi realisasi keuangan',
        'constraints' => 'Kendala dan penanganan',
        'follow_up_plan' => 'Rencana tindak lanjut',
    ];

    /**
     * @return array{
     *     ready: bool,
     *     percentage: int,
     *     policy_version: string,
     *     template: array{key: string, label: string, policy_version: string, sections: list<string>},
     *     checks: array<int, array{key: string, label: string, ready: bool, result: string, detail: string, source_reference: string}>,
     *     modules: array<int, array{key: string, label: string, description: string, percentage: int, ready: bool, source_count: int, source_reference: string, missing: array<int, string>}>,
     *     errors: array<string, string>,
     *     warnings: array<string, string>,
     *     active_evidence_ids: array<int, string>,
     *     current_evaluation_id: ?string
     * }
     */
    public function evaluate(Lpj $lpj): array
    {
        $lpj->loadMissing([
            'activity.unit',
            'activity.pic',
            'activity.mandate',
            'activity.plan.currentVersionRecord',
            'activity.budget.currentVersionRecord.lines',
            'activity.agendaItems',
            'activity.executionRecords',
            'activity.participants',
            'activity.transactions.reconciliation.exceptions',
            'activity.evidence',
            'activity.evaluations.kpiDefinitions.result.evidence',
            'currentVersionRecord.findings.correctiveActions',
            'versions.approvals',
        ]);

        $activity = $lpj->activity;
        $currentEvaluation = $activity->evaluations->sortByDesc('version')->first();
        $supersededEvidenceIds = $activity->evidence->pluck('supersedes_id')->filter()->all();
        $activeEvidence = $activity->evidence
            ->reject(fn ($evidence): bool => in_array($evidence->id, $supersededEvidenceIds, true))
            ->values();
        $reconciliationExceptions = $activity->transactions
            ->flatMap(fn ($transaction) => $transaction->reconciliation?->exceptions ?? [])
            ->values();
        $blockingExceptionCount = $reconciliationExceptions
            ->filter(fn ($exception): bool => in_array($exception->status->value, ReconciliationExceptionStatus::BLOCKING_VALUES, true))
            ->count();
        $approvedExceptionCount = $reconciliationExceptions
            ->where('status', ReconciliationExceptionStatus::ApprovedException)
            ->count();
        $openFindingCount = $lpj->currentVersionRecord?->findings
            ->filter(fn ($finding): bool => in_array($finding->status, [FindingStatus::Open, FindingStatus::CapaSubmitted], true))
            ->count() ?? 0;

        $checks = collect([
            $this->check('activity', 'Kegiatan selesai', $activity->status === ActivityStatus::Completed, 'Kegiatan harus berstatus selesai.', "Activity-ID {$activity->code}"),
            $this->check('mandate', 'Mandat tersedia', $activity->mandate !== null, 'Mandat kegiatan wajib tersedia.', $activity->mandate?->number ?? "Activity-ID {$activity->code}"),
            $this->check('plan', 'Planning disetujui', $activity->plan?->status === PlanStatus::Approved && $activity->plan?->currentVersionRecord !== null, 'Planning beserta versi yang disetujui wajib tersedia.', $activity->plan === null ? 'Planning belum tersedia' : "{$activity->plan->code} v{$activity->plan->current_version}"),
            $this->check('budget', 'RAB disetujui', $activity->budget?->status === BudgetStatus::Approved && $activity->budget?->currentVersionRecord !== null, 'RAB beserta versi yang disetujui wajib tersedia.', $activity->budget === null ? 'RAB belum tersedia' : "{$activity->budget->code} v{$activity->budget->current_version}"),
            $this->check('agenda', 'Agenda pelaksanaan lengkap', $activity->agendaItems->isNotEmpty() && $activity->agendaItems->every(fn ($item): bool => $item->status === ActivityAgendaStatus::Completed), 'Minimal satu agenda wajib tersedia dan seluruhnya harus selesai.', $activity->agendaItems->count().' agenda'),
            $this->check('execution_records', 'Catatan pelaksanaan tersedia', $activity->executionRecords->isNotEmpty(), 'Minimal satu catatan pelaksanaan wajib tersedia.', $activity->executionRecords->count().' catatan pelaksanaan'),
            $this->check('participants', 'Peserta tercatat', $activity->participants->isNotEmpty(), 'Minimal satu peserta wajib tersedia.', $activity->participants->count().' peserta'),
            $this->check('transactions', 'Transaksi tersedia', $activity->transactions->isNotEmpty(), 'Minimal satu transaksi wajib tersedia.', $activity->transactions->count().' transaksi'),
            $this->check('reconciliation', 'Rekonsiliasi final', $activity->transactions->isNotEmpty() && $activity->transactions->every(fn ($transaction): bool => in_array($transaction->reconciliation_status, [ReconciliationStatus::Matched, ReconciliationStatus::Resolved], true)) && $blockingExceptionCount === 0, 'Semua transaksi harus cocok atau memiliki resolusi final; permintaan pengecualian yang menunggu atau ditolak tetap memblokir.', $blockingExceptionCount.' exception belum final'),
            $this->check('evidence', 'Bukti aktif tersedia', $activeEvidence->isNotEmpty(), 'Minimal satu versi bukti aktif wajib tersedia.', $activeEvidence->count().' bukti aktif'),
            $this->check('evidence_scan', 'Pemeriksaan berkas lulus', $activeEvidence->isNotEmpty() && $activeEvidence->every(fn ($evidence): bool => $evidence->scan_status === EvidenceScanStatus::Registered), 'Seluruh bukti aktif wajib lolos pemeriksaan berkas.', $activeEvidence->where('scan_status', EvidenceScanStatus::Registered)->count().' bukti lolos scan'),
            $this->check('evidence_acceptance', 'Bukti dapat digunakan', $activeEvidence->isNotEmpty() && $activeEvidence->contains(fn ($evidence): bool => $evidence->review_status !== EvidenceReviewStatus::Rejected), 'Minimal satu bukti aktif tidak boleh berstatus ditolak.', $activeEvidence->where('review_status', EvidenceReviewStatus::Verified)->count().' bukti terverifikasi'),
            $this->check('evaluation', 'Evaluasi tersedia', $currentEvaluation !== null, 'Evaluasi kegiatan wajib tersedia.', $currentEvaluation === null ? 'Evaluasi belum tersedia' : "Evaluasi v{$currentEvaluation->version}"),
            $this->check('evaluation_status', 'Evaluasi diterima', $currentEvaluation?->status === EvaluationStatus::Accepted, 'Versi evaluasi terkini wajib diterima Verifier Substantif.', $currentEvaluation?->status->value ?? 'Belum tersedia'),
            $this->check('findings', 'Finding/CAPA terselesaikan', $openFindingCount === 0, 'Finding atau CAPA dari versi sebelumnya wajib diselesaikan.', $openFindingCount.' finding/CAPA terbuka'),
        ]);

        foreach (self::NARRATIVE_FIELDS as $field => $label) {
            $checks->push($this->check(
                $field,
                $label.' terisi',
                filled(trim((string) $lpj->getAttribute($field))),
                $label.' wajib dilengkapi pada composer.',
                'Draft narasi LPJ',
            ));
        }

        if ($activeEvidence->isNotEmpty() && $activeEvidence->contains(fn ($evidence): bool => $evidence->review_status !== EvidenceReviewStatus::Verified)) {
            $checks->push([
                'key' => 'evidence_review',
                'label' => 'Review bukti pendukung',
                'ready' => true,
                'result' => 'WARNING',
                'detail' => 'Sebagian bukti aktif belum diverifikasi; pengajuan tetap dapat dilanjutkan dan akan diperiksa pada tahap verifikasi.',
                'source_reference' => $activeEvidence->where('review_status', EvidenceReviewStatus::Verified)->count().' dari '.$activeEvidence->count().' bukti terverifikasi',
            ]);
        }

        if ($approvedExceptionCount > 0) {
            $checks->push([
                'key' => 'approved_reconciliation_exceptions',
                'label' => 'Pengecualian rekonsiliasi resmi',
                'ready' => true,
                'result' => 'WARNING',
                'detail' => 'Rekonsiliasi memuat pengecualian yang telah disetujui pejabat berwenang dan akan dibekukan dalam snapshot.',
                'source_reference' => $approvedExceptionCount.' approved exception',
            ]);
        }

        $errors = $checks
            ->reject(fn (array $check): bool => $check['ready'])
            ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['detail']])
            ->all();
        $warnings = $checks
            ->where('result', 'WARNING')
            ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['detail']])
            ->all();
        $blockingChecks = $checks->reject(fn (array $check): bool => $check['result'] === 'WARNING');
        $percentage = $blockingChecks->isEmpty()
            ? 0
            : (int) round($blockingChecks->where('ready', true)->count() / $blockingChecks->count() * 100);
        $activityType = (string) ($activity->plan?->currentVersionRecord?->activity_type ?? data_get($activity->plan_snapshot, 'activity_type', 'GENERAL'));
        $templatePolicy = $this->policyRegistry->get(GovernancePolicyType::Templates);

        return [
            'ready' => $errors === [],
            'percentage' => $percentage,
            'policy_version' => (string) config('lpj.precheck_policy.version'),
            'template' => [
                'key' => $activityType,
                'label' => $this->templateLabel($activityType, $templatePolicy),
                'policy_version' => (string) $templatePolicy['version'],
                'sections' => array_values($templatePolicy['sections'] ?? []),
            ],
            'checks' => $checks->values()->all(),
            'modules' => $this->modules($checks, $lpj),
            'errors' => $errors,
            'warnings' => $warnings,
            'active_evidence_ids' => $activeEvidence->pluck('id')->all(),
            'current_evaluation_id' => $currentEvaluation?->id,
        ];
    }

    /** @return array{key: string, label: string, ready: bool, result: string, detail: string, source_reference: string} */
    private function check(string $key, string $label, bool $ready, string $failureDetail, string $sourceReference): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ready' => $ready,
            'result' => $ready ? 'PASS' : 'BLOCKED',
            'detail' => $ready ? 'Sumber data memenuhi aturan pre-check.' : $failureDetail,
            'source_reference' => $sourceReference,
        ];
    }

    /**
     * @param  Collection<int, array{key: string, label: string, ready: bool, result: string, detail: string, source_reference: string}>  $checks
     * @return array<int, array{key: string, label: string, description: string, percentage: int, ready: bool, source_count: int, source_reference: string, missing: array<int, string>}>
     */
    private function modules(Collection $checks, Lpj $lpj): array
    {
        $activity = $lpj->activity;
        $definitions = [
            ['key' => 'identity', 'label' => 'A. Identitas', 'description' => 'Mandat, Activity-ID, unit, PIC, dan periode.', 'checks' => ['activity', 'mandate'], 'count' => 1, 'reference' => "Activity-ID {$activity->code}"],
            ['key' => 'planning', 'label' => 'B. Planning', 'description' => 'Tujuan, output, outcome, KPI, dan RAB terversi.', 'checks' => ['plan', 'budget'], 'count' => 2, 'reference' => 'Plan/RAB v'.$activity->plan_version.'/'.$activity->budget_version],
            ['key' => 'execution', 'label' => 'C. Pelaksanaan', 'description' => 'Agenda, peserta, progress, kendala, dan hasil.', 'checks' => ['agenda', 'execution_records', 'participants', 'implementation_narrative', 'constraints'], 'count' => $activity->agendaItems->count() + $activity->executionRecords->count() + $activity->participants->count(), 'reference' => "Activity-ID {$activity->code}"],
            ['key' => 'finance', 'label' => 'D. Keuangan', 'description' => 'Budget, actual, variance, transaksi, dan rekonsiliasi.', 'checks' => ['transactions', 'reconciliation', 'financial_narrative'], 'count' => $activity->transactions->count(), 'reference' => $activity->transactions->count().' transaksi sumber'],
            ['key' => 'evidence', 'label' => 'E. Evidence', 'description' => 'Dokumen, foto, BA, dan bukti transaksi aktif.', 'checks' => ['evidence', 'evidence_scan', 'evidence_acceptance'], 'count' => count($this->activeEvidenceIds($activity->evidence)), 'reference' => "Evidence Activity-ID {$activity->code}"],
            ['key' => 'evaluation', 'label' => 'F. Evaluasi', 'description' => 'Output, outcome, KPI, pembelajaran, dan tindak lanjut.', 'checks' => ['evaluation', 'evaluation_status', 'executive_summary', 'outcome_narrative', 'follow_up_plan'], 'count' => $activity->evaluations->count(), 'reference' => 'Evaluasi terbaru'],
            ['key' => 'findings', 'label' => 'G. Findings', 'description' => 'Finding dan CAPA dari versi sebelumnya.', 'checks' => ['findings'], 'count' => $lpj->currentVersionRecord?->findings->count() ?? 0, 'reference' => 'Riwayat verifikasi LPJ'],
            ['key' => 'audit', 'label' => 'H. Audit Pack', 'description' => 'Versi, approval, checksum, dan metadata audit.', 'checks' => [], 'count' => $lpj->versions->count(), 'reference' => (string) config('lpj.precheck_policy.version')],
        ];

        return collect($definitions)->map(function (array $definition) use ($checks): array {
            $moduleChecks = $checks->whereIn('key', $definition['checks']);
            $passed = $moduleChecks->where('ready', true)->count();
            $percentage = $moduleChecks->isEmpty() ? 100 : (int) round($passed / $moduleChecks->count() * 100);

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'percentage' => $percentage,
                'ready' => $percentage === 100,
                'source_count' => $definition['count'],
                'source_reference' => $definition['reference'],
                'missing' => $moduleChecks->where('ready', false)->pluck('detail')->values()->all(),
            ];
        })->all();
    }

    /** @return array<int, string> */
    private function activeEvidenceIds(Collection $evidence): array
    {
        $supersededIds = $evidence->pluck('supersedes_id')->filter()->all();

        return $evidence->reject(fn ($item): bool => in_array($item->id, $supersededIds, true))->pluck('id')->all();
    }

    /** @param array<string, mixed> $policy */
    private function templateLabel(string $activityType, array $policy): string
    {
        $mapping = collect($policy['mappings'] ?? [])->firstWhere('activity_type', mb_strtoupper($activityType));

        return (string) ($mapping['label'] ?? $policy['default_label'] ?? 'Kegiatan umum');
    }
}
