<?php

namespace App\Actions;

use App\Enums\EvaluationStatus;
use App\Enums\EvidenceScanStatus;
use App\Enums\LpjStatus;
use App\Models\Activity;
use App\Models\ActivityTaxonomy;
use App\Models\Evaluation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildActivityIntelligenceViewAction
{
    private const int MINIMUM_COHORT_SIZE = 3;

    private const int MAXIMUM_ANALYZED_RECORDS = 250;

    /**
     * @param  array{type?: string|null, unit?: string|null, period?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters, string $mode = 'ANALYSIS'): array
    {
        [$canViewAcrossUnits, $units, $selectedUnit, $scopeUnitIds] = $this->resolveScope($user, $filters);
        $period = $filters['period'] ?? '24';
        $baseQuery = $this->eligibleQuery($scopeUnitIds, $period);
        $typeOptions = $this->typeOptions($baseQuery);
        $firstPopulatedType = collect($typeOptions)->first(fn (array $type): bool => $type['count'] > 0);
        $selectedType = ActivityTaxonomy::query()->where('code', $filters['type'] ?? '')->first()
            ?? ActivityTaxonomy::query()->where('code', $firstPopulatedType['value'] ?? '')->first()
            ?? ActivityTaxonomy::query()->where('code', 'PEMBINAAN')->firstOrFail();
        $cohortQuery = (clone $baseQuery)->where('plan_snapshot->activity_type', $selectedType->code);
        $eligibleCount = (clone $cohortQuery)->count();
        $activities = $this->loadCohort($cohortQuery);
        $cohort = $activities->map(fn (Activity $activity): array => $this->formatActivity($activity, $user));
        $metrics = $this->metrics($cohort);
        $dataQuality = $cohort->isEmpty() ? 0 : (int) round((float) $cohort->avg('data_quality'));
        $eligibilityChecks = $this->eligibilityChecks($cohort, $eligibleCount, $dataQuality);
        $isSufficient = collect($eligibilityChecks)->every(fn (array $check): bool => $check['passed']);
        $insights = $this->insights($cohort, $metrics);

        return [
            'mode' => $mode,
            'scope' => [
                'can_view_across_units' => $canViewAcrossUnits,
                'selected_unit' => $selectedUnit?->only(['id', 'parent_id', 'code', 'name', 'type']),
                'label' => $selectedUnit?->name ?? 'Seluruh unit',
                'unit_count' => count($scopeUnitIds),
            ],
            'filters' => [
                'type' => $selectedType->code,
                'unit' => $selectedUnit?->id,
                'period' => $period,
            ],
            'options' => [
                'types' => $typeOptions,
                'periods' => [
                    ['value' => '12', 'label' => '12 bulan'],
                    ['value' => '24', 'label' => '24 bulan'],
                    ['value' => '36', 'label' => '36 bulan'],
                    ['value' => 'all', 'label' => 'Seluruh periode'],
                ],
                'units' => $canViewAcrossUnits
                    ? $units->map(fn (Unit $unit): array => $unit->only(['id', 'parent_id', 'code', 'name', 'type']))->values()->all()
                    : [],
            ],
            'eligibility' => [
                'is_sufficient' => $isSufficient,
                'comparable_count' => $eligibleCount,
                'analyzed_count' => $cohort->count(),
                'minimum_count' => self::MINIMUM_COHORT_SIZE,
                'missing_count' => max(0, self::MINIMUM_COHORT_SIZE - $eligibleCount),
                'data_quality' => $dataQuality,
                'confidence' => $isSufficient ? $this->confidence($cohort, $dataQuality) : null,
                'checks' => $eligibilityChecks,
                'blocking_reasons' => collect($eligibilityChecks)
                    ->reject(fn (array $check): bool => $check['passed'])
                    ->pluck('label')
                    ->values()
                    ->all(),
            ],
            'metrics' => $metrics,
            'trend' => $this->trend($cohort, $metrics['median_cost_per_participant']),
            'cohort' => $cohort->sortByDesc('starts_at')->values()->all(),
            'insights' => $insights,
            'scenarios' => $isSufficient ? $this->scenarios($metrics, $insights) : [],
            'methodology' => $this->methodology($selectedType, $period, $eligibleCount),
            'limitations' => $this->limitations($cohort, $eligibleCount, $period),
            'capabilities' => [
                'can_request_ai' => $user->can('ai.request') && $selectedUnit !== null,
                'can_review_ai' => $user->can('ai.review'),
                'must_select_unit_to_request' => $user->can('ai.request') && $canViewAcrossUnits && $selectedUnit === null,
            ],
        ];
    }

    /**
     * @param  array{unit?: string|null}  $filters
     * @return array{bool, Collection<int, Unit>, Unit|null, list<string>}
     */
    private function resolveScope(User $user, array $filters): array
    {
        $canViewAcrossUnits = $user->isGlobalAdministrator() || $user->can('lpjs.pb_verify');
        $units = Unit::query()
            ->when(! $canViewAcrossUnits, fn (Builder $query) => $query->whereKey($user->unit_id))
            ->orderBy('name')
            ->get(['id', 'parent_id', 'code', 'name', 'type']);
        $requestedUnitId = $filters['unit'] ?? null;
        $selectedUnit = $requestedUnitId
            ? $units->firstWhere('id', $requestedUnitId)
            : ($canViewAcrossUnits ? null : $units->firstWhere('id', $user->unit_id));

        abort_if($requestedUnitId !== null && $selectedUnit === null, 404);
        abort_if(! $canViewAcrossUnits && $selectedUnit === null, 403);

        $scopeUnitIds = $selectedUnit
            ? $this->descendantIds($units, $selectedUnit->id)
            : $units->modelKeys();

        return [$canViewAcrossUnits, $units, $selectedUnit, $scopeUnitIds];
    }

    /** @param list<string> $scopeUnitIds */
    private function eligibleQuery(array $scopeUnitIds, string $period): Builder
    {
        return Activity::query()
            ->whereIn('unit_id', $scopeUnitIds)
            ->whereHas('lpj', fn (Builder $lpjs) => $lpjs->whereIn('status', [
                LpjStatus::PbVerified->value,
                LpjStatus::Archived->value,
            ]))
            ->when($period !== 'all', fn (Builder $activities) => $activities
                ->where('starts_at', '>=', now()->subMonths((int) $period)->startOfDay()));
    }

    /** @return list<array{value: string, label: string, count: int}> */
    private function typeOptions(Builder $baseQuery): array
    {
        return ActivityTaxonomy::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (ActivityTaxonomy $type): array => [
                'value' => $type->code,
                'label' => $type->name,
                'count' => (clone $baseQuery)->where('plan_snapshot->activity_type', $type->code)->count(),
            ])->all();
    }

    /** @return Collection<int, Activity> */
    private function loadCohort(Builder $query): Collection
    {
        return $query
            ->select(['id', 'unit_id', 'code', 'title', 'starts_at', 'ends_at', 'budget_ceiling', 'plan_snapshot'])
            ->with([
                'unit:id,code,name,type',
                'lpj:id,activity_id,status,current_version,submitted_at,pb_verified_at,archived_at',
                'lpj.currentVersionRecord:id,lpj_id,version,checksum,submitted_at',
                'lpj.currentVersionRecord.findings:id,lpj_version_id,status,title',
                'evaluations' => fn ($evaluations) => $evaluations
                    ->where('status', EvaluationStatus::Accepted->value)
                    ->latest('version')
                    ->select([
                        'id', 'activity_id', 'version', 'status', 'score', 'completion_percent',
                        'satisfaction_score', 'lessons_learned', 'successful_practices',
                        'improvement_areas', 'formula_version', 'accepted_at',
                    ]),
            ])
            ->withCount([
                'participants',
                'evidence',
                'evidence as registered_evidence_count' => fn ($evidence) => $evidence
                    ->where('scan_status', EvidenceScanStatus::Registered->value),
            ])
            ->withSum([
                'transactions as actual_amount' => fn ($transactions) => $transactions->where('status', 'POSTED'),
            ], 'amount')
            ->latest('starts_at')
            ->orderByDesc('id')
            ->limit(self::MAXIMUM_ANALYZED_RECORDS)
            ->get()
            ->sortBy('starts_at')
            ->values();
    }

    /** @return array<string, mixed> */
    private function formatActivity(Activity $activity, User $user): array
    {
        /** @var Evaluation|null $evaluation */
        $evaluation = $activity->evaluations->first();
        $lpj = $activity->lpj;
        $version = $lpj?->currentVersionRecord;
        $participants = (int) $activity->participants_count;
        $evidenceCount = (int) $activity->evidence_count;
        $registeredEvidence = (int) $activity->registered_evidence_count;
        $actual = (float) ($activity->actual_amount ?? 0);
        $budget = (float) $activity->budget_ceiling;
        $duration = $activity->starts_at && $activity->ends_at
            ? max(1, (int) $activity->starts_at->copy()->startOfDay()->diffInDays($activity->ends_at->copy()->startOfDay()) + 1)
            : 1;
        $completedAt = $lpj?->archived_at ?? $lpj?->pb_verified_at;
        $slaDays = $lpj?->submitted_at && $completedAt
            ? (int) $lpj->submitted_at->diffInDays($completedAt)
            : null;
        $budgetAccuracy = $budget > 0
            ? (int) round(max(0, 100 - (abs($actual - $budget) / $budget * 100)))
            : null;
        $evidenceQuality = $evidenceCount > 0
            ? (int) round(min(100, ($registeredEvidence / $evidenceCount * 70) + (min($registeredEvidence, 3) / 3 * 30)))
            : 0;
        $dataQuality = ($evaluation ? 30 : 0)
            + ($participants > 0 ? 20 : 0)
            + ($registeredEvidence > 0 ? 20 : 0)
            + ($actual > 0 ? 15 : 0)
            + ($version?->checksum ? 15 : 0);

        return [
            'id' => $activity->id,
            'code' => $activity->code,
            'title' => $activity->title,
            'url' => $user->isGlobalAdministrator() || $user->unit_id === $activity->unit_id
                ? '/activities/'.$activity->id
                : ($lpj ? '/lpjs/'.$lpj->id : '/portfolio'),
            'starts_at' => $activity->starts_at?->toIso8601String(),
            'duration_days' => $duration,
            'unit' => $activity->unit->only(['id', 'code', 'name', 'type']),
            'lpj' => [
                'id' => $lpj?->id,
                'url' => $lpj ? '/lpjs/'.$lpj->id : null,
                'status' => $lpj?->status->value,
                'version' => $version?->version,
                'checksum' => $version?->checksum,
            ],
            'participants' => $participants,
            'outcome_index' => $evaluation?->score === null ? null : (int) round(min(100, (float) $evaluation->score)),
            'completion_percent' => $evaluation?->completion_percent === null ? null : (int) round((float) $evaluation->completion_percent),
            'budget' => $budget,
            'actual' => $actual,
            'budget_accuracy' => $budgetAccuracy,
            'evidence_quality' => $evidenceQuality,
            'findings_count' => $version?->findings->count() ?? 0,
            'finding_titles' => $version?->findings->pluck('title')->filter()->values()->all() ?? [],
            'lpj_sla_days' => $slaDays,
            'cost_per_participant' => $participants > 0 ? (int) round($actual / $participants) : null,
            'data_quality' => $dataQuality,
            'lessons_learned' => $evaluation?->lessons_learned,
            'successful_practices' => $evaluation?->successful_practices,
            'improvement_areas' => $evaluation?->improvement_areas,
            'formula_version' => $evaluation?->formula_version,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @return array<string, int|float|null>
     */
    private function metrics(Collection $cohort): array
    {
        return [
            'median_participants' => $this->median($cohort->pluck('participants')),
            'median_duration_days' => $this->median($cohort->pluck('duration_days')),
            'outcome_index' => $this->median($cohort->pluck('outcome_index')),
            'budget_accuracy' => $this->average($cohort->pluck('budget_accuracy')),
            'evidence_quality' => $this->average($cohort->pluck('evidence_quality')),
            'median_lpj_sla_days' => $this->median($cohort->pluck('lpj_sla_days')),
            'median_cost_per_participant' => $this->median($cohort->pluck('cost_per_participant')),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @return list<array<string, mixed>>
     */
    private function trend(Collection $cohort, int|float|null $medianCost): array
    {
        return $cohort->map(function (array $activity) use ($medianCost): array {
            $cost = $activity['cost_per_participant'];

            return [
                'code' => $activity['code'],
                'label' => $activity['starts_at'] ? date('m/Y', strtotime($activity['starts_at'])) : $activity['code'],
                'starts_at' => $activity['starts_at'],
                'outcome_index' => $activity['outcome_index'],
                'cost_per_participant' => $cost,
                'cost_index' => $cost && $medianCost
                    ? (int) round(((float) $cost / (float) $medianCost) * 100)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @param  array<string, int|float|null>  $metrics
     * @return list<array{tone: string, label: string, title: string, detail: string}>
     */
    private function insights(Collection $cohort, array $metrics): array
    {
        $withOutcome = $cohort->whereNotNull('outcome_index')->values();
        $outcomeDelta = $withOutcome->count() > 1
            ? (int) $withOutcome->last()['outcome_index'] - (int) $withOutcome->first()['outcome_index']
            : null;
        $finding = $this->mostFrequent($cohort->pluck('finding_titles')->flatten()->filter());
        $practice = $this->mostFrequent($cohort->pluck('successful_practices')->filter());
        $improvement = $this->mostFrequent($cohort->pluck('improvement_areas')->filter());

        return array_values(array_filter([
            $outcomeDelta === null ? null : [
                'tone' => $outcomeDelta >= 0 ? 'positive' : 'warning',
                'label' => 'Arah outcome',
                'title' => $outcomeDelta === 0 ? 'Outcome relatif stabil' : sprintf('Perubahan %+d poin pada rentang terpilih', $outcomeDelta),
                'detail' => 'Perbandingan deskriptif antara kegiatan terlama dan terbaru; bukan bukti hubungan sebab-akibat.',
            ],
            $practice ? [
                'tone' => 'positive',
                'label' => 'Praktik kuat',
                'title' => $practice,
                'detail' => 'Praktik yang paling sering atau paling baru tercatat pada evaluasi yang telah diterima.',
            ] : null,
            $finding ? [
                'tone' => 'warning',
                'label' => 'Pola temuan',
                'title' => $finding,
                'detail' => 'Tema yang paling sering muncul pada versi LPJ sumber dalam cohort ini.',
            ] : null,
            $improvement ? [
                'tone' => 'neutral',
                'label' => 'Area perbaikan',
                'title' => $improvement,
                'detail' => sprintf('Gunakan sebagai kontrol perencanaan; median SLA cohort adalah %s hari.', $metrics['median_lpj_sla_days'] ?? '—'),
            ] : null,
        ]));
    }

    /**
     * @param  array<string, int|float|null>  $metrics
     * @param  list<array<string, string>>  $insights
     * @return list<array<string, mixed>>
     */
    private function scenarios(array $metrics, array $insights): array
    {
        $participants = max(1, (int) round((float) ($metrics['median_participants'] ?? 1)));
        $duration = max(1, (int) round((float) ($metrics['median_duration_days'] ?? 1)));
        $cost = max(0, (int) round((float) ($metrics['median_cost_per_participant'] ?? 0)));
        $insightControl = collect($insights)->firstWhere('label', 'Area perbaikan')['title'] ?? 'Tetapkan checkpoint evidence dan evaluasi outcome.';

        return [
            $this->scenario(
                'EFFICIENCY',
                'Skenario A — Efisiensi',
                'Pertahankan skala median sambil memperketat evidence capture dan rekonsiliasi harian.',
                max(1, (int) floor($participants * 0.9)),
                max(1, (int) ceil($participants * 1.1)),
                $duration,
                $cost,
                'Rendah–Menengah',
                ['Checkpoint evidence setiap hari', 'Tetapkan verifier sebelum pelaksanaan', $insightControl],
            ),
            $this->scenario(
                'SCALE',
                'Skenario B — Perluasan',
                'Naikkan kapasitas secara terkendali dengan rasio verifikasi yang mengikuti jumlah peserta.',
                max(1, (int) floor($participants * 1.2)),
                max(1, (int) ceil($participants * 1.4)),
                $duration,
                $cost,
                'Menengah',
                ['Tambahkan kapasitas verifier', 'Uji kesiapan registrasi digital', 'Pertahankan normalisasi biaya per peserta'],
            ),
            $this->scenario(
                'QUALITY',
                'Skenario C — Fokus Kualitas',
                'Batasi cohort peserta untuk memperdalam coaching, evidence, dan evaluasi outcome.',
                max(1, (int) floor($participants * 0.75)),
                max(1, (int) ceil($participants * 0.9)),
                $duration + 1,
                $cost,
                'Rendah',
                ['Tetapkan rubrik outcome sebelum kegiatan', 'Validasi evidence per sesi', 'Review evaluasi sebelum submit LPJ'],
            ),
        ];
    }

    /** @param list<string> $controls */
    private function scenario(string $id, string $title, string $summary, int $minimumParticipants, int $maximumParticipants, int $duration, int $cost, string $risk, array $controls): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'summary' => $summary,
            'target_participants' => ['minimum' => $minimumParticipants, 'maximum' => $maximumParticipants],
            'duration_days' => $duration,
            'budget_reference' => [
                'minimum' => $cost * $minimumParticipants,
                'maximum' => $cost * $maximumParticipants,
            ],
            'risk' => $risk,
            'controls' => array_values(array_unique($controls)),
            'assumptions' => [
                'Biaya menggunakan median biaya per peserta cohort terverifikasi.',
                'Target adalah rentang awal dan wajib disunting manusia sebelum menjadi draft planning.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function methodology(ActivityTaxonomy $type, string $period, int $eligibleCount): array
    {
        return [
            'rule_version' => 'activity-intelligence-rules-v1',
            'source_statuses' => [LpjStatus::PbVerified->value, LpjStatus::Archived->value],
            'period_label' => $period === 'all' ? 'Seluruh periode' : $period.' bulan terakhir',
            'applied_comparable_factors' => ['Jenis kegiatan sama', 'Scope organisasi terpilih', 'Periode analisis sama'],
            'desired_comparable_factors' => $type->comparable_factors,
            'normalization' => ['Biaya per peserta', 'Durasi dalam hari', 'Outcome pada indeks 0–100', 'Akurasi terhadap pagu'],
            'sample_size' => $eligibleCount,
            'causality_notice' => 'Pola bersifat deskriptif dan tidak membuktikan hubungan sebab-akibat.',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @return list<string>
     */
    private function limitations(Collection $cohort, int $eligibleCount, string $period): array
    {
        $limitations = [
            'Cohort memakai kecocokan exact pada taxonomy jenis kegiatan; dimensi rinci masih mengikuti kualitas data sumber.',
            'Skenario adalah pratinjau berbasis aturan, bukan persetujuan, prediksi kausal, atau perubahan data sumber.',
        ];

        if ($eligibleCount < 5) {
            $limitations[] = 'Ukuran sampel di bawah lima kegiatan; confidence dibatasi dan hasil perlu ditelaah lebih ketat.';
        }

        if ($cohort->contains(fn (array $row): bool => $row['outcome_index'] === null)) {
            $limitations[] = 'Sebagian LPJ tidak memiliki evaluasi outcome diterima sehingga metrik outcome tidak mencakup seluruh cohort.';
        }

        if ($eligibleCount > self::MAXIMUM_ANALYZED_RECORDS) {
            $limitations[] = sprintf('Metrik dihitung dari %d kegiatan terbaru dari total %d record yang memenuhi syarat.', self::MAXIMUM_ANALYZED_RECORDS, $eligibleCount);
        }

        if ($period !== 'all') {
            $limitations[] = 'Kegiatan di luar '.$period.' bulan terakhir tidak termasuk analisis.';
        }

        return $limitations;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @return list<array{label: string, passed: bool, detail: string}>
     */
    private function eligibilityChecks(Collection $cohort, int $eligibleCount, int $dataQuality): array
    {
        $evaluationCoverage = $cohort->isEmpty() ? 0 : (int) round($cohort->whereNotNull('outcome_index')->count() / $cohort->count() * 100);
        $evidenceCoverage = $cohort->isEmpty() ? 0 : (int) round($cohort->where('evidence_quality', '>', 0)->count() / $cohort->count() * 100);

        return [
            ['label' => 'Sumber LPJ terverifikasi', 'passed' => $eligibleCount > 0, 'detail' => $eligibleCount.' LPJ PB_VERIFIED/ARCHIVED'],
            ['label' => 'Ukuran cohort minimum', 'passed' => $eligibleCount >= self::MINIMUM_COHORT_SIZE, 'detail' => $eligibleCount.' dari minimum '.self::MINIMUM_COHORT_SIZE],
            ['label' => 'Cakupan evaluasi diterima', 'passed' => $evaluationCoverage >= 80, 'detail' => $evaluationCoverage.'% record'],
            ['label' => 'Cakupan evidence terdaftar', 'passed' => $evidenceCoverage >= 80, 'detail' => $evidenceCoverage.'% record'],
            ['label' => 'Kualitas data minimum', 'passed' => $dataQuality >= 80, 'detail' => $dataQuality.'% dari minimum 80%'],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $cohort */
    private function confidence(Collection $cohort, int $dataQuality): int
    {
        $sampleScore = min(45, (int) round($cohort->count() / 5 * 45));
        $qualityScore = (int) round($dataQuality * 0.45);
        $recentScore = $cohort->contains(fn (array $row): bool => $row['starts_at'] && strtotime($row['starts_at']) >= now()->subYear()->timestamp) ? 10 : 0;

        return min(100, $sampleScore + $qualityScore + $recentScore);
    }

    private function median(Collection $values): int|float|null
    {
        $sorted = $values->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): float => (float) $value)->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);
        $value = $count % 2 === 0
            ? ((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2
            : (float) $sorted[$middle];

        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
    }

    private function average(Collection $values): int|float|null
    {
        $numeric = $values->filter(fn (mixed $value): bool => is_numeric($value));

        return $numeric->isEmpty() ? null : (int) round((float) $numeric->avg());
    }

    private function mostFrequent(Collection $values): ?string
    {
        return $values
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return list<string>
     */
    private function descendantIds(Collection $units, string $unitId): array
    {
        $children = $units->groupBy(fn (Unit $unit): string => $unit->parent_id ?? 'root');
        $ids = [];
        $queue = [$unitId];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $ids[] = $currentId;

            foreach ($children->get($currentId, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
