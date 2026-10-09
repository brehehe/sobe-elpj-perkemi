<?php

namespace App\Actions;

use App\Enums\LpjStatus;
use App\Models\Lpj;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuildLpjIndexViewAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $canViewAllUnits = $user->isGlobalAdministrator() || $user->can('lpjs.pb_verify');
        $requestedUnitId = $filters['unit'] ?? null;

        if (! $canViewAllUnits && $requestedUnitId !== null && $requestedUnitId !== $user->unit_id) {
            abort(404);
        }

        $scopeQuery = Lpj::query()
            ->when(! $canViewAllUnits, fn (Builder $query) => $query->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)))
            ->when($requestedUnitId, fn (Builder $query, string $unitId) => $query->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $unitId)));
        $filteredQuery = $this->applyFilters(clone $scopeQuery, $filters);
        $lpjs = $this->applySort(clone $filteredQuery, $filters)
            ->with(['activity:id,unit_id,code,title,starts_at,ends_at', 'activity.unit:id,code,name', 'owner:id,name', 'currentVersionRecord.verifications'])
            ->paginate(15)
            ->withQueryString();

        return [
            'summary' => [
                'total' => (clone $scopeQuery)->count(),
                'draft' => (clone $scopeQuery)->whereIn('status', [LpjStatus::Draft->value, LpjStatus::ReadyToSubmit->value, LpjStatus::Returned->value])->count(),
                'in_process' => (clone $scopeQuery)->whereIn('status', [
                    LpjStatus::Submitted->value, LpjStatus::InVerification->value, LpjStatus::ClarificationRequired->value,
                    LpjStatus::FindingOpen->value, LpjStatus::ReadyForApproval->value, LpjStatus::Approved->value,
                ])->count(),
                'finalized' => (clone $scopeQuery)->whereIn('status', [LpjStatus::Rejected->value, LpjStatus::PbVerified->value, LpjStatus::Archived->value])->count(),
                'filtered' => (clone $filteredQuery)->count(),
            ],
            'scope' => [
                'can_view_all_units' => $canViewAllUnits,
                'label' => $requestedUnitId
                    ? Unit::query()->whereKey($requestedUnitId)->value('name')
                    : ($canViewAllUnits ? 'Seluruh unit' : ($user->unit?->name ?? 'Unit Anda')),
            ],
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'unit' => $requestedUnitId,
                'sort' => (string) ($filters['sort'] ?? 'latest'),
            ],
            'options' => [
                'statuses' => collect(LpjStatus::cases())->map(fn (LpjStatus $status): array => ['value' => $status->value, 'label' => $status->label()])->all(),
                'units' => $canViewAllUnits ? Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']) : [],
            ],
            'lpjs' => $lpjs,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $lpjs, string $search) => $lpjs->where(fn (Builder $matching) => $matching
                ->whereLike('id', '%'.$search.'%')
                ->orWhereHas('activity', fn (Builder $activities) => $activities
                    ->whereLike('code', '%'.$search.'%')
                    ->orWhereLike('title', '%'.$search.'%'))
                ->orWhereHas('owner', fn (Builder $owners) => $owners->whereLike('name', '%'.$search.'%'))))
            ->when($filters['status'] ?? null, fn (Builder $lpjs, string $status) => $lpjs->where('status', $status));
    }

    /** @param array<string, mixed> $filters */
    private function applySort(Builder $query, array $filters): Builder
    {
        return match ($filters['sort'] ?? 'latest') {
            'oldest' => $query->oldest('updated_at')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('total_amount')->latest('updated_at'),
            'amount_asc' => $query->orderBy('total_amount')->latest('updated_at'),
            default => $query->latest('updated_at')->orderByDesc('id'),
        };
    }
}
