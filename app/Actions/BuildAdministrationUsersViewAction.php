<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class BuildAdministrationUsersViewAction
{
    /** @param array<string, mixed> $filters */
    public function handle(User $actor, array $filters): array
    {
        $actor->loadMissing('unit');
        $canViewAllUnits = $actor->isGlobalAdministrator();
        $requestedUnitId = $filters['unit'] ?? null;

        if (! $canViewAllUnits && $requestedUnitId !== null && $requestedUnitId !== $actor->unit_id) {
            abort(404);
        }

        $scopeQuery = User::query()
            ->when(! $canViewAllUnits, fn (Builder $query) => $query->where('unit_id', $actor->unit_id))
            ->when($canViewAllUnits && $requestedUnitId, fn (Builder $query) => $query->where('unit_id', $requestedUnitId));

        $users = (clone $scopeQuery)
            ->select(['id', 'unit_id', 'name', 'email', 'email_verified_at', 'is_active', 'last_login_at', 'created_at'])
            ->with(['unit:id,code,name', 'roles:id,name'])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('email', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
                'unit' => $user->unit?->only(['id', 'code', 'name']),
                'role' => $user->roles->first()?->name,
                'can_update' => $actor->can('update', $user),
                'can_delete' => $actor->can('delete', $user),
                'is_current_user' => $actor->is($user),
            ]);

        return [
            'summary' => [
                'total' => (clone $scopeQuery)->count(),
                'active' => (clone $scopeQuery)->where('is_active', true)->count(),
                'inactive' => (clone $scopeQuery)->where('is_active', false)->count(),
                'unverified' => (clone $scopeQuery)->whereNull('email_verified_at')->count(),
            ],
            'scope' => [
                'can_view_all_units' => $canViewAllUnits,
                'label' => $canViewAllUnits ? 'Lintas unit' : ($actor->unit?->name ?? 'Tanpa unit'),
                'default_unit_id' => $canViewAllUnits ? ($requestedUnitId ?: $actor->unit_id) : $actor->unit_id,
            ],
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'role' => (string) ($filters['role'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'unit' => $requestedUnitId,
            ],
            'options' => [
                'units' => $canViewAllUnits
                    ? Unit::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active'])
                    : collect([$actor->unit])->filter()->map->only(['id', 'code', 'name', 'is_active'])->values(),
                'roles' => Role::query()
                    ->when(! $canViewAllUnits, fn (Builder $query) => $query->where('name', '!=', 'Super Admin'))
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'users' => $users,
        ];
    }
}
