<?php

namespace App\Http\Controllers;

use App\Actions\ResolveIntegrationOwnerAction;
use App\Enums\IntegrationStatus;
use App\Models\IntegrationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationHubController extends Controller
{
    public function __invoke(Request $request, ResolveIntegrationOwnerAction $resolveIntegrationOwner): Response
    {
        $user = $request->user();
        abort_unless($user->can('integrations.manage'), 403);

        $filters = $request->validate([
            'provider' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(IntegrationStatus::class)],
        ]);
        $base = $this->scopedRuns($user);
        $providerMetrics = $this->providerMetrics($base);
        $latestRuns = $this->latestRunsByProvider($base);
        $ownerIds = $latestRuns->pluck('owner_id')->filter()->unique()->values();
        $owners = User::query()->whereIn('id', $ownerIds)->get(['id', 'name'])->keyBy('id');
        $defaultOwner = $resolveIntegrationOwner->handle($user->isGlobalAdministrator() ? null : $user->unit_id, $user);
        $providers = $this->providers($providerMetrics, $latestRuns, $owners, $defaultOwner);

        $runs = (clone $base)
            ->with(['owner:id,name', 'triggeredBy:id,name', 'unit:id,code,name'])
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc(DB::raw('COALESCE(last_attempted_at, created_at)'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (IntegrationRun $run): array => [
                'id' => $run->id,
                'provider' => $run->provider,
                'idempotency_key' => $run->idempotency_key,
                'status' => $run->status->value,
                'retry_status' => $run->retry_status->value,
                'attempts' => $run->attempts,
                'records_received' => $run->records_received,
                'records_succeeded' => $run->records_succeeded,
                'records_failed' => $run->records_failed,
                'duration_ms' => $run->duration_ms,
                'error_category' => $run->error_category,
                'last_error' => $run->last_error,
                'contract_version' => $run->contract_version,
                'correlation_id' => $run->correlation_id,
                'last_attempted_at' => $run->last_attempted_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'next_retry_at' => $run->next_retry_at?->toIso8601String(),
                'owner' => $run->owner?->only(['id', 'name']),
                'triggered_by' => $run->triggeredBy?->only(['id', 'name']),
                'unit' => $run->unit?->only(['id', 'code', 'name']),
                'can_retry' => in_array($run->status, [IntegrationStatus::Failed, IntegrationStatus::DeadLetter], true)
                    && is_array(data_get($run->request_metadata, 'payload')),
            ]);

        return Inertia::render('integrations/index', [
            'summary' => $this->summary($base),
            'providers' => $providers,
            'runs' => $runs,
            'filters' => [
                'provider' => $filters['provider'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'filterOptions' => [
                'providers' => $providers->map(fn (array $provider): array => [
                    'value' => $provider['key'],
                    'label' => $provider['name'],
                ]),
                'statuses' => collect(IntegrationStatus::cases())->map(fn (IntegrationStatus $status): array => [
                    'value' => $status->value,
                    'label' => str_replace('_', ' ', $status->value),
                ]),
            ],
        ]);
    }

    private function scopedRuns(User $user): Builder
    {
        return IntegrationRun::query()->when(
            ! $user->isGlobalAdministrator(),
            fn (Builder $query) => $query->where('unit_id', $user->unit_id),
        );
    }

    /** @return Collection<string, object> */
    private function providerMetrics(Builder $base): Collection
    {
        return (clone $base)
            ->select('provider')
            ->selectRaw("MAX(CASE WHEN status = 'SUCCEEDED' THEN completed_at END) AS last_successful_at")
            ->selectRaw('MAX(COALESCE(last_attempted_at, created_at)) AS last_attempted_at')
            ->selectRaw('SUM(records_succeeded) AS records_succeeded')
            ->selectRaw('SUM(records_failed) AS records_failed')
            ->selectRaw("SUM(CASE WHEN status IN ('PENDING', 'PROCESSING') OR retry_status IN ('SCHEDULED', 'QUEUED', 'RETRYING') THEN 1 ELSE 0 END) AS backlog")
            ->selectRaw("SUM(CASE WHEN status = 'DEAD_LETTER' THEN 1 ELSE 0 END) AS dead_letter_count")
            ->selectRaw('ROUND(AVG(duration_ms)) AS average_duration_ms')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');
    }

    /** @return Collection<string, object> */
    private function latestRunsByProvider(Builder $base): Collection
    {
        $ranked = (clone $base)
            ->select([
                'id', 'provider', 'status', 'retry_status', 'error_category', 'contract_version', 'owner_id',
                'last_attempted_at', 'completed_at', 'next_retry_at', 'created_at',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY provider ORDER BY COALESCE(last_attempted_at, created_at) DESC, created_at DESC) AS row_number');

        return DB::query()
            ->fromSub($ranked, 'ranked_integration_runs')
            ->where('row_number', 1)
            ->get()
            ->keyBy('provider');
    }

    /** @return array<string, int> */
    private function summary(Builder $base): array
    {
        $summary = (clone $base)->selectRaw("COUNT(*) AS total_runs, SUM(CASE WHEN status = 'SUCCEEDED' THEN 1 ELSE 0 END) AS succeeded_runs, SUM(CASE WHEN status IN ('FAILED', 'PARTIAL_FAILED', 'DEAD_LETTER') THEN 1 ELSE 0 END) AS failed_runs, SUM(CASE WHEN status IN ('PENDING', 'PROCESSING') OR retry_status IN ('SCHEDULED', 'QUEUED', 'RETRYING') THEN 1 ELSE 0 END) AS backlog, SUM(CASE WHEN status = 'DEAD_LETTER' THEN 1 ELSE 0 END) AS dead_letter")->first();

        return [
            'total_runs' => (int) ($summary?->total_runs ?? 0),
            'succeeded_runs' => (int) ($summary?->succeeded_runs ?? 0),
            'failed_runs' => (int) ($summary?->failed_runs ?? 0),
            'backlog' => (int) ($summary?->backlog ?? 0),
            'dead_letter' => (int) ($summary?->dead_letter ?? 0),
        ];
    }

    /**
     * @param  Collection<string, object>  $metrics
     * @param  Collection<string, object>  $latestRuns
     * @param  Collection<string, User>  $owners
     * @return Collection<int, array<string, mixed>>
     */
    private function providers(Collection $metrics, Collection $latestRuns, Collection $owners, ?User $defaultOwner): Collection
    {
        $definitions = collect(config('integrations.providers'));
        $metrics->keys()->each(function (string $provider) use ($definitions): void {
            if (! $definitions->has($provider)) {
                $definitions->put($provider, [
                    'name' => $provider,
                    'description' => 'Provider eksternal yang terdeteksi dari run aktual.',
                    'contract_version' => config('integrations.default_contract_version'),
                ]);
            }
        });

        return $definitions->map(function (array $definition, string $provider) use ($defaultOwner, $latestRuns, $metrics, $owners): array {
            $aggregate = $metrics->get($provider);
            $latest = $latestRuns->get($provider);
            $owner = $latest?->owner_id ? $owners->get($latest->owner_id) : $defaultOwner;

            return [
                'key' => $provider,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'health' => $this->health($aggregate, $latest),
                'observed' => $aggregate !== null,
                'last_successful_at' => $aggregate?->last_successful_at,
                'last_attempted_at' => $aggregate?->last_attempted_at,
                'average_duration_ms' => $aggregate?->average_duration_ms !== null ? (int) $aggregate->average_duration_ms : null,
                'backlog' => (int) ($aggregate?->backlog ?? 0),
                'records_succeeded' => (int) ($aggregate?->records_succeeded ?? 0),
                'records_failed' => (int) ($aggregate?->records_failed ?? 0),
                'error_category' => $latest?->error_category,
                'retry_status' => $latest?->retry_status ?? 'NOT_REQUIRED',
                'contract_version' => $latest?->contract_version ?? $definition['contract_version'],
                'owner' => $owner?->only(['id', 'name']),
                'latest_status' => $latest?->status,
                'dead_letter_count' => (int) ($aggregate?->dead_letter_count ?? 0),
            ];
        })->values();
    }

    private function health(?object $aggregate, ?object $latest): string
    {
        if ($aggregate === null || $latest === null) {
            return 'NOT_OBSERVED';
        }

        if (in_array($latest->status, [IntegrationStatus::Pending->value, IntegrationStatus::Processing->value], true)) {
            return 'SYNCING';
        }

        if (in_array($latest->status, [IntegrationStatus::Failed->value, IntegrationStatus::PartialFailed->value, IntegrationStatus::DeadLetter->value], true)) {
            return 'DEGRADED';
        }

        if ($aggregate->last_attempted_at !== null && now()->diffInMinutes($aggregate->last_attempted_at) > config('integrations.stale_after_minutes', 1440)) {
            return 'STALE';
        }

        return $latest->status === IntegrationStatus::Succeeded->value ? 'OPERATIONAL' : 'NOT_OBSERVED';
    }
}
