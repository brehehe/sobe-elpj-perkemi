<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Activity;
use App\Models\FinancialTransaction;
use App\Models\Finding;
use App\Models\Lpj;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class BuildPortfolioViewAction
{
    /**
     * @param  array{q?: string|null, unit?: string|null, status?: string|null, sort?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $canViewAcrossUnits = $user->isGlobalAdministrator() || $user->can('lpjs.pb_verify');
        $units = Unit::query()
            ->when(! $canViewAcrossUnits, fn (Builder $query) => $query->whereKey($user->unit_id))
            ->orderBy('name')
            ->get(['id', 'parent_id', 'code', 'name', 'type', 'is_active']);

        $requestedUnitId = $filters['unit'] ?? null;
        $selectedUnit = $requestedUnitId
            ? $units->firstWhere('id', $requestedUnitId)
            : ($canViewAcrossUnits ? null : $units->firstWhere('id', $user->unit_id));

        abort_if($requestedUnitId !== null && $selectedUnit === null, 404);
        abort_if(! $canViewAcrossUnits && $selectedUnit === null, 403);

        $scopeUnitIds = $selectedUnit
            ? $this->descendantIds($units, $selectedUnit->id)
            : $units->modelKeys();
        $directMetrics = $this->directUnitMetrics($units->modelKeys());
        $summary = $this->aggregateMetrics($directMetrics, $scopeUnitIds);
        $childUnits = $this->childUnits($units, $selectedUnit, $directMetrics);
        $portfolio = $this->portfolioQuery($scopeUnitIds, $filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Lpj $lpj): array => $this->portfolioRow($lpj));

        return [
            'summary' => [
                ...$summary,
                'variance' => $summary['budget'] - $summary['actual'],
                'verified_rate' => $summary['total_lpjs'] > 0
                    ? (int) round(($summary['verified_lpjs'] / $summary['total_lpjs']) * 100)
                    : 0,
            ],
            'scope' => [
                'can_view_across_units' => $canViewAcrossUnits,
                'selected_unit' => $selectedUnit?->only(['id', 'parent_id', 'code', 'name', 'type']),
                'breadcrumbs' => $this->breadcrumbs($units, $selectedUnit),
                'label' => $selectedUnit?->name ?? 'Seluruh unit',
            ],
            'units' => $childUnits,
            'portfolio' => $portfolio,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'sort' => $filters['sort'] ?? 'updated_desc',
                'unit' => $selectedUnit?->id,
            ],
            'statusOptions' => collect(LpjStatus::cases())->map(fn (LpjStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
        ];
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

    /**
     * @param  list<string>  $scopeUnitIds
     * @param  array{q?: string|null, unit?: string|null, status?: string|null, sort?: string|null}  $filters
     */
    private function portfolioQuery(array $scopeUnitIds, array $filters): Builder
    {
        $query = Lpj::query()
            ->select(['lpjs.id', 'lpjs.activity_id', 'lpjs.owner_id', 'lpjs.status', 'lpjs.current_version', 'lpjs.total_amount', 'lpjs.updated_at'])
            ->whereHas('activity', fn (Builder $activity) => $activity->whereIn('unit_id', $scopeUnitIds))
            ->when($filters['q'] ?? null, fn (Builder $lpjs, string $search) => $lpjs
                ->where(fn (Builder $query) => $query
                    ->whereLike('lpjs.id', '%'.$search.'%')
                    ->orWhereHas('activity', fn (Builder $activity) => $activity
                        ->whereLike('code', '%'.$search.'%')
                        ->orWhereLike('title', '%'.$search.'%'))))
            ->when($filters['status'] ?? null, fn (Builder $lpjs, string $status) => $lpjs->where('lpjs.status', $status))
            ->with([
                'owner:id,name',
                'activity' => fn (BelongsTo $activity) => $activity
                    ->select(['id', 'unit_id', 'code', 'title', 'budget_ceiling', 'starts_at', 'ends_at'])
                    ->with('unit:id,code,name,type')
                    ->withSum('transactions as actual_amount', 'amount')
                    ->withCount(['transactions', 'evidence']),
                'currentVersionRecord' => fn (HasOne $version) => $version
                    ->select(['lpj_versions.id', 'lpj_versions.lpj_id', 'lpj_versions.version'])
                    ->withCount([
                        'verifications as completed_verifications_count' => fn (Builder $verifications) => $verifications->where('status', VerificationStatus::Verified->value),
                        'findings as open_findings_count' => fn (Builder $findings) => $findings->whereIn('status', [FindingStatus::Open->value, FindingStatus::CapaSubmitted->value]),
                    ]),
            ]);

        match ($filters['sort'] ?? 'updated_desc') {
            'budget_desc' => $query->orderByDesc(Activity::query()
                ->select('budget_ceiling')
                ->whereColumn('activities.id', 'lpjs.activity_id')
                ->limit(1)),
            'actual_desc' => $query->orderByDesc(FinancialTransaction::query()
                ->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('financial_transactions.activity_id', 'lpjs.activity_id')),
            default => $query->orderByDesc('lpjs.updated_at'),
        };

        return $query->orderBy('lpjs.id');
    }

    /** @return array<string, mixed> */
    private function portfolioRow(Lpj $lpj): array
    {
        $budget = (float) $lpj->activity->budget_ceiling;
        $actual = (float) ($lpj->activity->actual_amount ?? 0);

        return [
            'id' => $lpj->id,
            'status' => $lpj->status->value,
            'current_version' => $lpj->current_version,
            'updated_at' => $lpj->updated_at?->toISOString(),
            'owner' => $lpj->owner->only(['id', 'name']),
            'activity' => [
                ...$lpj->activity->only(['id', 'code', 'title', 'starts_at', 'ends_at']),
                'unit' => $lpj->activity->unit->only(['id', 'code', 'name', 'type']),
            ],
            'budget' => $budget,
            'actual' => $actual,
            'variance' => $budget - $actual,
            'transactions_count' => $lpj->activity->transactions_count,
            'evidence_count' => $lpj->activity->evidence_count,
            'verification' => [
                'completed' => $lpj->currentVersionRecord?->completed_verifications_count ?? 0,
                'total' => count(VerificationStage::cases()),
            ],
            'open_findings' => $lpj->currentVersionRecord?->open_findings_count ?? 0,
        ];
    }

    /**
     * @param  list<string>  $unitIds
     * @return Collection<string, array{activity_count: int, total_lpjs: int, verified_lpjs: int, open_findings: int, budget: float, actual: float}>
     */
    private function directUnitMetrics(array $unitIds): Collection
    {
        $metrics = collect($unitIds)->mapWithKeys(fn (string $unitId): array => [$unitId => $this->emptyMetrics()]);

        if ($unitIds === []) {
            return $metrics;
        }

        Activity::query()
            ->whereIn('unit_id', $unitIds)
            ->selectRaw('unit_id, COUNT(*) as activity_count, COALESCE(SUM(budget_ceiling), 0) as budget')
            ->groupBy('unit_id')
            ->get()
            ->each(function (Activity $row) use ($metrics): void {
                $metrics->put($row->unit_id, [
                    ...$metrics->get($row->unit_id),
                    'activity_count' => (int) $row->activity_count,
                    'budget' => (float) $row->budget,
                ]);
            });

        Lpj::query()
            ->join('activities', 'activities.id', '=', 'lpjs.activity_id')
            ->whereIn('activities.unit_id', $unitIds)
            ->selectRaw('activities.unit_id as unit_id, COUNT(*) as total_lpjs')
            ->selectRaw('SUM(CASE WHEN lpjs.status IN (?, ?) THEN 1 ELSE 0 END) as verified_lpjs', [LpjStatus::PbVerified->value, LpjStatus::Archived->value])
            ->groupBy('activities.unit_id')
            ->get()
            ->each(function (Lpj $row) use ($metrics): void {
                $metrics->put($row->unit_id, [
                    ...$metrics->get($row->unit_id),
                    'total_lpjs' => (int) $row->total_lpjs,
                    'verified_lpjs' => (int) $row->verified_lpjs,
                ]);
            });

        FinancialTransaction::query()
            ->join('activities', 'activities.id', '=', 'financial_transactions.activity_id')
            ->whereIn('activities.unit_id', $unitIds)
            ->selectRaw('activities.unit_id as unit_id, COALESCE(SUM(financial_transactions.amount), 0) as actual')
            ->groupBy('activities.unit_id')
            ->get()
            ->each(function (FinancialTransaction $row) use ($metrics): void {
                $metrics->put($row->unit_id, [
                    ...$metrics->get($row->unit_id),
                    'actual' => (float) $row->actual,
                ]);
            });

        Finding::query()
            ->join('lpj_versions', 'lpj_versions.id', '=', 'findings.lpj_version_id')
            ->join('lpjs', 'lpjs.id', '=', 'lpj_versions.lpj_id')
            ->join('activities', 'activities.id', '=', 'lpjs.activity_id')
            ->whereIn('activities.unit_id', $unitIds)
            ->whereIn('findings.status', [FindingStatus::Open->value, FindingStatus::CapaSubmitted->value])
            ->selectRaw('activities.unit_id as unit_id, COUNT(*) as open_findings')
            ->groupBy('activities.unit_id')
            ->get()
            ->each(function (Finding $row) use ($metrics): void {
                $metrics->put($row->unit_id, [
                    ...$metrics->get($row->unit_id),
                    'open_findings' => (int) $row->open_findings,
                ]);
            });

        return $metrics;
    }

    /**
     * @param  Collection<string, array{activity_count: int, total_lpjs: int, verified_lpjs: int, open_findings: int, budget: float, actual: float}>  $directMetrics
     * @param  list<string>  $unitIds
     * @return array{activity_count: int, total_lpjs: int, verified_lpjs: int, open_findings: int, budget: float, actual: float}
     */
    private function aggregateMetrics(Collection $directMetrics, array $unitIds): array
    {
        return collect($unitIds)->reduce(function (array $total, string $unitId) use ($directMetrics): array {
            $metric = $directMetrics->get($unitId, $this->emptyMetrics());

            return [
                'activity_count' => $total['activity_count'] + $metric['activity_count'],
                'total_lpjs' => $total['total_lpjs'] + $metric['total_lpjs'],
                'verified_lpjs' => $total['verified_lpjs'] + $metric['verified_lpjs'],
                'open_findings' => $total['open_findings'] + $metric['open_findings'],
                'budget' => $total['budget'] + $metric['budget'],
                'actual' => $total['actual'] + $metric['actual'],
            ];
        }, $this->emptyMetrics());
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @param  Collection<string, array{activity_count: int, total_lpjs: int, verified_lpjs: int, open_findings: int, budget: float, actual: float}>  $directMetrics
     * @return list<array<string, mixed>>
     */
    private function childUnits(Collection $units, ?Unit $selectedUnit, Collection $directMetrics): array
    {
        $candidates = $selectedUnit
            ? $units->where('parent_id', $selectedUnit->id)
            : $units->whereNull('parent_id');

        return $candidates->map(function (Unit $unit) use ($units, $directMetrics): array {
            $subtreeIds = $this->descendantIds($units, $unit->id);
            $metrics = $this->aggregateMetrics($directMetrics, $subtreeIds);

            return [
                ...$unit->only(['id', 'parent_id', 'code', 'name', 'type', 'is_active']),
                ...$metrics,
                'variance' => $metrics['budget'] - $metrics['actual'],
                'subtree_unit_count' => count($subtreeIds),
                'has_children' => $units->contains('parent_id', $unit->id),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return list<array<string, mixed>>
     */
    private function breadcrumbs(Collection $units, ?Unit $selectedUnit): array
    {
        if ($selectedUnit === null) {
            return [];
        }

        $byId = $units->keyBy('id');
        $breadcrumbs = [];
        $current = $selectedUnit;

        while ($current !== null) {
            $breadcrumbs[] = $current->only(['id', 'code', 'name', 'type']);
            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        return array_reverse($breadcrumbs);
    }

    /** @return array{activity_count: int, total_lpjs: int, verified_lpjs: int, open_findings: int, budget: float, actual: float} */
    private function emptyMetrics(): array
    {
        return [
            'activity_count' => 0,
            'total_lpjs' => 0,
            'verified_lpjs' => 0,
            'open_findings' => 0,
            'budget' => 0.0,
            'actual' => 0.0,
        ];
    }
}
