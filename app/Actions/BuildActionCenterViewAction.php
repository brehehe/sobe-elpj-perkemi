<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Models\User;
use App\Services\SmartTaskPresentationService;
use App\Services\SmartTaskVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class BuildActionCenterViewAction
{
    public function __construct(
        private SmartTaskPresentationService $presenter,
        private SmartTaskVisibilityService $visibility,
    ) {}

    /**
     * @param  array{q?: string|null, status?: string|null, priority?: string|null, due?: string|null, sort?: string|null, direction?: string|null, unit?: string|null, per_page?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $canViewAcrossUnits = $user->isGlobalAdministrator() || $user->can('lpjs.pb_verify');
        $units = Unit::query()
            ->when(! $canViewAcrossUnits, fn (Builder $query) => $query->whereKey($user->unit_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $requestedUnitId = $filters['unit'] ?? null;
        abort_if(! $canViewAcrossUnits && $requestedUnitId !== null && $requestedUnitId !== $user->unit_id, 404);
        abort_if($requestedUnitId !== null && $units->doesntContain('id', $requestedUnitId), 404);

        $query = $this->visibility->queryFor($user)
            ->when($requestedUnitId, fn (Builder $tasks, string $unitId) => $tasks->where('unit_id', $unitId));
        $this->applySharedFilters($query, $filters);

        $metricsQuery = clone $query;
        $tasksQuery = (clone $query)
            ->with(['unit:id,code,name', 'activity:id,unit_id,code,title', 'activity.lpj:id,activity_id,status,current_version', 'assignee:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $tasks, string $status) => $tasks->where('status', $status));
        $this->applySort($tasksQuery, $filters['sort'] ?? 'priority', $filters['direction'] ?? 'asc');

        $tasks = $tasksQuery
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString()
            ->through(fn (SmartTask $task): array => $this->presenter->present($task));

        return [
            'tasks' => $tasks,
            'metrics' => [
                'open' => (clone $metricsQuery)->where('status', TaskStatus::Open->value)->count(),
                'in_progress' => (clone $metricsQuery)->where('status', TaskStatus::InProgress->value)->count(),
                'overdue' => (clone $metricsQuery)
                    ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->count(),
                'due_today' => (clone $metricsQuery)
                    ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
                    ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
                    ->count(),
                'done' => (clone $metricsQuery)->where('status', TaskStatus::Done->value)->count(),
            ],
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'due' => $filters['due'] ?? '',
                'sort' => $filters['sort'] ?? 'priority',
                'direction' => $filters['direction'] ?? 'asc',
                'unit' => $requestedUnitId ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'options' => [
                'statuses' => [
                    ['value' => '', 'label' => 'Semua status'],
                    ['value' => TaskStatus::Open->value, 'label' => 'Terbuka'],
                    ['value' => TaskStatus::InProgress->value, 'label' => 'Dikerjakan'],
                    ['value' => TaskStatus::Done->value, 'label' => 'Selesai'],
                    ['value' => TaskStatus::Cancelled->value, 'label' => 'Dibatalkan'],
                ],
                'priorities' => [
                    ['value' => '', 'label' => 'Semua prioritas'],
                    ['value' => 'CRITICAL', 'label' => 'Kritis'],
                    ['value' => 'HIGH', 'label' => 'Tinggi'],
                    ['value' => 'MEDIUM', 'label' => 'Sedang'],
                    ['value' => 'LOW', 'label' => 'Rendah'],
                ],
                'due' => [
                    ['value' => '', 'label' => 'Semua tenggat'],
                    ['value' => 'overdue', 'label' => 'Lewat tenggat'],
                    ['value' => 'today', 'label' => 'Jatuh tempo hari ini'],
                    ['value' => 'next_7_days', 'label' => '7 hari ke depan'],
                    ['value' => 'no_due', 'label' => 'Tanpa tenggat'],
                ],
            ],
            'scope' => [
                'can_view_across_units' => $canViewAcrossUnits,
                'units' => $units,
            ],
        ];
    }

    /** @param array{q?: string|null, priority?: string|null, due?: string|null} $filters */
    private function applySharedFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $tasks, string $search) => $tasks->where(function (Builder $matching) use ($search): void {
                $matching->whereLike('title', '%'.$search.'%')
                    ->orWhereLike('description', '%'.$search.'%')
                    ->orWhereLike('type', '%'.$search.'%')
                    ->orWhereHas('activity', fn (Builder $activity) => $activity
                        ->whereLike('code', '%'.$search.'%')
                        ->orWhereLike('title', '%'.$search.'%'));
            }))
            ->when($filters['priority'] ?? null, fn (Builder $tasks, string $priority) => $tasks->where('priority', $priority));

        match ($filters['due'] ?? null) {
            'overdue' => $query->whereNotNull('due_at')->where('due_at', '<', now()),
            'today' => $query->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
            'next_7_days' => $query->whereBetween('due_at', [now(), now()->addDays(7)]),
            'no_due' => $query->whereNull('due_at'),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'CRITICAL' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END ".($direction === 'desc' ? 'DESC' : 'ASC'));
        } else {
            if ($sort === 'due_at') {
                $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END');
            }

            $query->orderBy($sort, $direction);
        }

        $query->orderBy('due_at')->orderBy('id');
    }
}
