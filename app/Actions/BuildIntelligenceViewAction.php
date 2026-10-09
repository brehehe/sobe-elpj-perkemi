<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use App\Services\AiUsageGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BuildIntelligenceViewAction
{
    public function __construct(private AiUsageGuard $usageGuard) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        [$canViewAllUnits, $selectedUnit] = $this->resolveScope($user, $filters);
        $scopeQuery = $this->scopeQuery($user, $selectedUnit);
        $filteredQuery = $this->applyFilters(clone $scopeQuery, $filters);
        $reviews = $this->applySort(clone $filteredQuery, $filters)
            ->with([
                'activity:id,unit_id,code,title,status',
                'activity.unit:id,code,name,type',
                'activity.lpj:id,activity_id,status,current_version',
                'lpjVersion:id,lpj_id,version,status,checksum,submitted_at',
                'requester:id,name',
                'reviewer:id,name',
                'planningDraft:id,source_ai_review_id,code,status',
            ])
            ->paginate(12)
            ->withQueryString()
            ->through(fn (AiReview $review): array => $this->formatReview($review, $user));
        $averageConfidence = (clone $filteredQuery)->whereNotNull('confidence')->avg('confidence');

        return [
            'summary' => [
                'total' => (clone $filteredQuery)->count(),
                'pending_review' => (clone $filteredQuery)->where('status', AiReviewStatus::ReviewRequired->value)->where('human_review_status', 'PENDING')->count(),
                'accepted' => (clone $filteredQuery)->whereIn('human_review_status', ['ACCEPTED', 'MODIFIED'])->count(),
                'average_confidence' => $averageConfidence === null ? null : (int) round((float) $averageConfidence * 100),
            ],
            'scope' => [
                'can_view_all_units' => $canViewAllUnits,
                'selected_unit' => $selectedUnit?->only(['id', 'code', 'name', 'type']),
                'label' => $selectedUnit?->name ?? 'Seluruh unit',
            ],
            'capabilities' => [
                'can_request' => $user->can('ai.request'),
                'can_review' => $user->can('ai.review'),
            ],
            'service' => [
                'mode' => config('services.openai.enabled') && filled(config('services.openai.key')) ? 'OPENAI' : 'LOCAL_RULES',
                'prompt_version' => (string) config('services.openai.prompt_version'),
                'core_workflow_independent' => true,
                'usage' => $user->isGlobalAdministrator() ? $this->usageGuard->status() : null,
            ],
            'reviews' => $reviews,
            'filters' => $this->normalizedFilters($filters, $selectedUnit),
            'options' => [
                'types' => (clone $scopeQuery)->select('type')->distinct()->orderBy('type')->pluck('type')->all(),
                'models' => (clone $scopeQuery)->whereNotNull('model')->select('model')->distinct()->orderBy('model')->pluck('model')->all(),
                'units' => $canViewAllUnits ? Unit::query()->orderBy('name')->get(['id', 'code', 'name', 'type'])->all() : [],
                'activities' => $user->can('ai.request') ? $this->requestableActivities($user, $selectedUnit) : [],
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function resolveScope(User $user, array $filters): array
    {
        $canViewAllUnits = $user->isGlobalAdministrator();
        $requestedUnitId = $filters['unit'] ?? null;

        if (! $canViewAllUnits) {
            abort_if($requestedUnitId !== null && $requestedUnitId !== $user->unit_id, 404);

            return [false, Unit::query()->findOrFail($user->unit_id)];
        }

        return [true, $requestedUnitId ? Unit::query()->findOrFail($requestedUnitId) : null];
    }

    private function scopeQuery(User $user, ?Unit $selectedUnit): Builder
    {
        return AiReview::query()->whereHas('activity', fn (Builder $activities) => $activities
            ->when($selectedUnit !== null, fn (Builder $query) => $query->where('unit_id', $selectedUnit->id))
            ->when(! $user->isGlobalAdministrator() && $selectedUnit === null, fn (Builder $query) => $query->where('unit_id', $user->unit_id)));
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $reviews, string $search) => $reviews
                ->where(fn (Builder $matching) => $matching
                    ->whereLike('model', '%'.$search.'%')
                    ->orWhereLike('prompt_hash', '%'.$search.'%')
                    ->orWhereHas('activity', fn (Builder $activities) => $activities
                        ->whereLike('code', '%'.$search.'%')
                        ->orWhereLike('title', '%'.$search.'%'))
                    ->orWhereHas('requester', fn (Builder $requesters) => $requesters->whereLike('name', '%'.$search.'%'))))
            ->when($filters['type'] ?? null, fn (Builder $reviews, string $type) => $reviews->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $reviews, string $status) => $reviews->where('status', $status))
            ->when($filters['review_status'] ?? null, fn (Builder $reviews, string $status) => $reviews->where('human_review_status', $status))
            ->when($filters['model'] ?? null, fn (Builder $reviews, string $model) => $reviews->where('model', $model));
    }

    /** @param array<string, mixed> $filters */
    private function applySort(Builder $query, array $filters): Builder
    {
        return ($filters['sort'] ?? 'latest') === 'oldest'
            ? $query->oldest('created_at')->orderBy('id')
            : $query->latest('created_at')->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function formatReview(AiReview $review, User $user): array
    {
        $output = $this->formatOutput($review->output);

        return [
            'id' => $review->id,
            'type' => $review->type,
            'status' => $review->status->value,
            'model' => $review->model,
            'prompt_hash' => $review->prompt_hash,
            'prompt_version' => $review->prompt_version,
            'response_id' => $review->response_id,
            'usage' => $review->usage ?? [],
            'latency_ms' => $review->latency_ms,
            'retry_count' => $review->retry_count,
            'confidence' => $review->confidence === null ? null : (int) round((float) $review->confidence * 100),
            'limitations' => $review->limitations,
            'human_review_status' => $review->human_review_status,
            'review_notes' => $review->review_notes,
            'reviewed_output' => $review->reviewed_output === null ? null : $this->formatOutput($review->reviewed_output),
            'created_at' => $review->created_at?->toIso8601String(),
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            'activity' => [
                ...$review->activity->only(['id', 'code', 'title']),
                'status' => $review->activity->status->value,
                'url' => '/activities/'.$review->activity_id,
                'lpj_url' => $review->activity->lpj ? '/lpjs/'.$review->activity->lpj->id : null,
                'unit' => $review->activity->unit->only(['id', 'code', 'name', 'type']),
            ],
            'lpj_version' => $review->lpjVersion?->only(['id', 'version', 'status', 'checksum', 'submitted_at']),
            'requester' => $review->requester->only(['id', 'name']),
            'reviewer' => $review->reviewer?->only(['id', 'name']),
            'sources' => $this->formatSources($review->input_sources ?? []),
            'output' => $output,
            'can_review' => $user->can('ai.review')
                && $review->requested_by !== $user->id
                && $review->status === AiReviewStatus::ReviewRequired
                && $review->human_review_status === 'PENDING',
            'can_create_planning_draft' => $user->can('create', Plan::class)
                && $review->status === AiReviewStatus::Succeeded
                && in_array($review->human_review_status, ['ACCEPTED', 'MODIFIED'], true)
                && $review->planningDraft === null,
            'can_cancel' => $user->can('ai.request')
                && ($review->requested_by === $user->id || $user->isGlobalAdministrator())
                && in_array($review->status, [AiReviewStatus::Queued, AiReviewStatus::Running], true),
            'planning_draft' => $review->planningDraft?->only(['id', 'code', 'status']),
        ];
    }

    /** @param array<string, mixed>|null $output */
    private function formatOutput(?array $output): array
    {
        return [
            'summary' => Arr::get($output, 'summary'),
            'risks' => array_values(array_filter((array) Arr::get($output, 'risks', []), 'is_string')),
            'recommendations' => array_values(array_filter((array) Arr::get($output, 'recommendations', []), 'is_string')),
            'sources' => array_values(array_filter((array) Arr::get($output, 'sources', []), 'is_string')),
            'assumptions' => array_values(array_filter((array) Arr::get($output, 'assumptions', []), 'is_string')),
            'comparable_factors' => array_values(array_filter((array) Arr::get($output, 'comparable_factors', []), 'is_string')),
            'normalization' => Arr::get($output, 'normalization'),
        ];
    }

    /** @param array<int|string, mixed> $sources */
    private function formatSources(array $sources): array
    {
        if (array_is_list($sources)) {
            return collect($sources)
                ->filter(fn (mixed $source): bool => is_string($source))
                ->map(fn (string $source): array => ['type' => Str::headline($source), 'reference' => $source, 'checksum' => null])
                ->values()
                ->all();
        }

        return collect($sources)->map(function (mixed $source, string $type): array {
            if (! is_array($source)) {
                return ['type' => Str::headline($type), 'reference' => (string) $source, 'checksum' => null];
            }

            if ($type === 'cohort') {
                return [
                    'type' => 'Comparable Cohort',
                    'reference' => collect([$source['code'] ?? null, $source['period'] ?? null, $source['scope']['label'] ?? null])->filter()->implode(' · '),
                    'checksum' => $source['id'] ?? null,
                ];
            }

            $reference = collect([$source['code'] ?? null, $source['id'] ?? null, isset($source['version']) ? 'v'.$source['version'] : null])
                ->filter()
                ->implode(' · ');

            return [
                'type' => Str::headline($type),
                'reference' => $reference ?: 'Sumber internal',
                'checksum' => $source['checksum'] ?? null,
            ];
        })->values()->all();
    }

    private function requestableActivities(User $user, ?Unit $selectedUnit): array
    {
        return Activity::query()
            ->when($selectedUnit !== null, fn (Builder $query) => $query->where('unit_id', $selectedUnit->id))
            ->when(! $user->isGlobalAdministrator() && $selectedUnit === null, fn (Builder $query) => $query->where('unit_id', $user->unit_id))
            ->with(['unit:id,code,name', 'lpj:id,activity_id,status,current_version'])
            ->latest('starts_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'unit_id', 'code', 'title', 'status', 'starts_at'])
            ->map(fn (Activity $activity): array => [
                ...$activity->only(['id', 'code', 'title']),
                'status' => $activity->status->value,
                'unit' => $activity->unit->only(['id', 'code', 'name']),
                'lpj' => $activity->lpj?->only(['id', 'status', 'current_version']),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|null>
     */
    private function normalizedFilters(array $filters, ?Unit $selectedUnit): array
    {
        return [
            'q' => $filters['q'] ?? '',
            'unit' => $selectedUnit?->id,
            'type' => $filters['type'] ?? '',
            'status' => $filters['status'] ?? '',
            'review_status' => $filters['review_status'] ?? '',
            'model' => $filters['model'] ?? '',
            'sort' => $filters['sort'] ?? 'latest',
        ];
    }
}
