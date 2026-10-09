<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Jobs\ProcessAiReview;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\User;
use App\Services\AiUsageGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestNextActivityRecommendationAction
{
    public function __construct(
        private BuildActivityIntelligenceViewAction $buildIntelligence,
        private RecordAuditEventAction $recordAuditEvent,
        private AiUsageGuard $usageGuard,
    ) {}

    /**
     * @param  array{type?: string|null, unit?: string|null, period?: string|null}  $filters
     */
    public function handle(User $user, array $filters): AiReview
    {
        $intelligence = $this->buildIntelligence->handle($user, $filters, 'NEXT_ACTIVITY');

        if (! Arr::get($intelligence, 'eligibility.is_sufficient', false)) {
            $reasons = collect(Arr::get($intelligence, 'eligibility.checks', []))
                ->reject(fn (array $check): bool => $check['passed'])
                ->pluck('label')
                ->implode(', ');

            throw ValidationException::withMessages([
                'recommendation' => 'Paket belum dapat dibuat. Penuhi pemeriksaan eligibility: '.($reasons ?: 'data comparable belum cukup').'.',
            ]);
        }

        $cohort = collect(Arr::get($intelligence, 'cohort', []));
        $selectedUnitId = Arr::get($intelligence, 'scope.selected_unit.id');

        if (! is_string($selectedUnitId) || $selectedUnitId === '') {
            throw ValidationException::withMessages([
                'unit' => 'Pilih satu unit organisasi sebelum mencatat paket untuk review manusia.',
            ]);
        }

        $anchorData = $cohort->first(fn (array $row): bool => Arr::get($row, 'unit.id') === $selectedUnitId);

        if (! is_array($anchorData) || ! isset($anchorData['id'])) {
            throw ValidationException::withMessages([
                'recommendation' => 'Unit terpilih belum memiliki kegiatan sumber langsung untuk mengikat otorisasi paket rekomendasi.',
            ]);
        }

        $activity = Activity::query()
            ->with(['lpj.currentVersionRecord', 'plan.currentVersionRecord'])
            ->findOrFail($anchorData['id']);
        $lpjVersion = $activity->lpj?->currentVersionRecord;

        if ($lpjVersion === null) {
            throw ValidationException::withMessages([
                'recommendation' => 'Snapshot LPJ terverifikasi tidak tersedia pada kegiatan sumber.',
            ]);
        }

        $sourceManifest = $this->sourceManifest($intelligence, $cohort, $activity);
        $output = $this->output($intelligence);
        $this->usageGuard->assertCanRun();
        $promptHash = hash('sha256', json_encode([
            'type' => 'NEXT_ACTIVITY',
            'rule_version' => Arr::get($intelligence, 'methodology.rule_version'),
            'filters' => Arr::get($intelligence, 'filters'),
            'cohort' => collect(Arr::get($sourceManifest, 'cohort.records'))->sortBy('code')->values()->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        [$review, $wasCreated] = DB::transaction(function () use ($activity, $intelligence, $lpjVersion, $output, $promptHash, $sourceManifest, $user): array {
            $existingReview = AiReview::query()
                ->where('requested_by', $user->id)
                ->where('type', 'NEXT_ACTIVITY')
                ->where('prompt_hash', $promptHash)
                ->whereIn('status', [
                    AiReviewStatus::Queued->value,
                    AiReviewStatus::Running->value,
                    AiReviewStatus::ReviewRequired->value,
                    AiReviewStatus::Succeeded->value,
                ])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($existingReview !== null) {
                return [$existingReview, false];
            }

            $ruleVersion = (string) Arr::get($intelligence, 'methodology.rule_version');
            $review = AiReview::create([
                'activity_id' => $activity->id,
                'lpj_version_id' => $lpjVersion->id,
                'requested_by' => $user->id,
                'type' => 'NEXT_ACTIVITY',
                'status' => AiReviewStatus::Queued,
                'prompt_hash' => $promptHash,
                'prompt_version' => (string) config('services.openai.prompt_version'),
                'input_sources' => $sourceManifest,
                'output' => $output,
                'usage' => [
                    'input_records' => (int) Arr::get($intelligence, 'eligibility.analyzed_count', 0),
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0,
                ],
                'latency_ms' => 0,
                'retry_count' => 0,
                'confidence' => ((int) Arr::get($intelligence, 'eligibility.confidence', 0)) / 100,
                'limitations' => collect(Arr::get($intelligence, 'limitations', []))->implode("\n"),
            ]);

            $sourceLpjIds = collect(Arr::get($sourceManifest, 'cohort.records', []))->pluck('lpj_id')->filter()->values()->all();
            $this->recordAuditEvent->handle($activity, 'ai.next_activity_recommendation_queued', $user, null, AiReviewStatus::Queued->value, [
                'ai_review_id' => $review->id,
                'prompt_hash' => $promptHash,
                'prompt_version' => $review->prompt_version,
                'fallback_rule_version' => $ruleVersion,
                'activity_type' => Arr::get($intelligence, 'filters.type'),
                'period' => Arr::get($intelligence, 'filters.period'),
                'source_lpj_ids' => $sourceLpjIds,
                'data_quality' => Arr::get($intelligence, 'eligibility.data_quality'),
                'confidence' => Arr::get($intelligence, 'eligibility.confidence'),
                'queue' => 'ai',
            ]);

            return [$review, true];
        }, attempts: 3);

        if ($wasCreated) {
            ProcessAiReview::dispatch(
                $review->id,
                $this->processingSources($intelligence, $output, $user),
            )->afterCommit();
        }

        return $review->fresh(['activity', 'lpjVersion']);
    }

    /**
     * @param  array<string, mixed>  $intelligence
     * @param  array<string, mixed>  $fallbackOutput
     * @return array<string, mixed>
     */
    private function processingSources(array $intelligence, array $fallbackOutput, User $user): array
    {
        $ruleVersion = (string) Arr::get($intelligence, 'methodology.rule_version');
        $cohort = collect(Arr::get($intelligence, 'cohort', []))
            ->take(50)
            ->map(fn (array $row): array => Arr::only($row, [
                'code', 'starts_at', 'participants', 'outcome_index', 'budget', 'actual',
                'budget_accuracy', 'evidence_quality', 'findings_count', 'lpj_sla_days', 'data_quality',
            ]))
            ->values()
            ->all();

        return [
            'activity_type' => Arr::get($intelligence, 'filters.type'),
            'period' => Arr::get($intelligence, 'methodology.period_label'),
            'metrics' => Arr::get($intelligence, 'metrics', []),
            'cohort' => $cohort,
            'insights' => Arr::get($intelligence, 'insights', []),
            'baseline_scenarios' => Arr::get($intelligence, 'scenarios', []),
            'comparable_factors' => Arr::get($intelligence, 'methodology.applied_comparable_factors', []),
            'normalization' => Arr::get($intelligence, 'methodology.normalization', []),
            'data_quality' => Arr::get($intelligence, 'eligibility.data_quality'),
            'limitations' => Arr::get($intelligence, 'limitations', []),
            '_fallback_output' => $fallbackOutput,
            '_fallback_model' => 'local-rules/'.$ruleVersion,
            '_safety_identifier' => hash_hmac('sha256', $user->id, (string) config('app.key')),
        ];
    }

    /**
     * @param  array<string, mixed>  $intelligence
     * @param  Collection<int, array<string, mixed>>  $cohort
     * @return array<string, mixed>
     */
    private function sourceManifest(array $intelligence, Collection $cohort, Activity $activity): array
    {
        $records = $cohort->map(fn (array $row): array => [
            'activity_id' => $row['id'],
            'code' => $row['code'],
            'period_start' => $row['starts_at'],
            'lpj_id' => Arr::get($row, 'lpj.id'),
            'lpj_version' => Arr::get($row, 'lpj.version'),
            'lpj_checksum' => Arr::get($row, 'lpj.checksum'),
            'unit_id' => Arr::get($row, 'unit.id'),
            'data_quality' => $row['data_quality'],
        ])->values()->all();
        $ruleVersion = (string) Arr::get($intelligence, 'methodology.rule_version');

        return [
            'activity' => [
                'id' => $activity->id,
                'code' => $activity->code,
                'period_start' => $activity->starts_at?->toDateString(),
                'period_end' => $activity->ends_at?->toDateString(),
                'source_hash' => hash('sha256', json_encode($activity->plan_snapshot, JSON_THROW_ON_ERROR)),
            ],
            'lpj' => [
                'id' => $activity->lpj?->id,
                'version' => $activity->lpj?->currentVersionRecord?->version,
                'checksum' => $activity->lpj?->currentVersionRecord?->checksum,
            ],
            'cohort' => [
                'id' => hash('sha256', json_encode($records, JSON_THROW_ON_ERROR)),
                'code' => Arr::get($intelligence, 'filters.type').' · '.count($records).' LPJ',
                'version' => $ruleVersion,
                'activity_type' => Arr::get($intelligence, 'filters.type'),
                'period' => Arr::get($intelligence, 'methodology.period_label'),
                'scope' => Arr::get($intelligence, 'scope'),
                'records' => $records,
                'comparable_factors' => Arr::get($intelligence, 'methodology.applied_comparable_factors'),
                'normalization' => Arr::get($intelligence, 'methodology.normalization'),
                'data_quality' => Arr::get($intelligence, 'eligibility.data_quality'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $intelligence
     * @return array<string, mixed>
     */
    private function output(array $intelligence): array
    {
        $scenarios = collect(Arr::get($intelligence, 'scenarios', []));
        $recommendations = $scenarios->map(function (array $scenario): string {
            $participants = Arr::get($scenario, 'target_participants');
            $controls = collect(Arr::get($scenario, 'controls', []))->implode('; ');

            return sprintf(
                '%s: target %d–%d peserta, durasi %d hari, risiko %s. Kontrol: %s',
                $scenario['title'],
                $participants['minimum'],
                $participants['maximum'],
                $scenario['duration_days'],
                $scenario['risk'],
                $controls,
            );
        })->all();
        $warningInsights = collect(Arr::get($intelligence, 'insights', []))
            ->where('tone', 'warning')
            ->pluck('title')
            ->values();
        $assumptions = $scenarios
            ->pluck('assumptions')
            ->flatten()
            ->merge([
                'Cohort hanya memuat LPJ berstatus PB_VERIFIED atau ARCHIVED.',
                'Semua opsi wajib direview manusia sebelum digunakan sebagai draft planning.',
            ])
            ->unique()
            ->values()
            ->all();
        $type = (string) Arr::get($intelligence, 'filters.type');
        $count = (int) Arr::get($intelligence, 'eligibility.comparable_count');

        return [
            'summary' => sprintf('Tersedia tiga opsi kegiatan %s berikutnya berdasarkan %d LPJ sejenis yang terverifikasi pada %s.', $type, $count, Arr::get($intelligence, 'methodology.period_label')),
            'risks' => $warningInsights->isNotEmpty()
                ? $warningInsights->all()
                : ['Perubahan skala peserta atau durasi dapat mengubah kebutuhan verifier, evidence, dan anggaran.'],
            'recommendations' => $recommendations,
            'sources' => collect(Arr::get($intelligence, 'cohort', []))->pluck('code')->values()->all(),
            'assumptions' => $assumptions,
            'comparable_factors' => [
                ...Arr::get($intelligence, 'methodology.applied_comparable_factors', []),
                'Dimensi taxonomy: '.collect(Arr::get($intelligence, 'methodology.desired_comparable_factors', []))->implode(', '),
            ],
            'normalization' => collect(Arr::get($intelligence, 'methodology.normalization', []))->implode('; '),
            'scenarios' => $scenarios->all(),
            'planning_baseline' => [
                'activity_type' => $type,
                'target_participants' => (int) round((float) Arr::get($intelligence, 'metrics.median_participants', 1)),
                'duration_days' => (int) round((float) Arr::get($intelligence, 'metrics.median_duration_days', 1)),
            ],
            'data_quality' => Arr::get($intelligence, 'eligibility.data_quality'),
            'rule_version' => Arr::get($intelligence, 'methodology.rule_version'),
        ];
    }
}
