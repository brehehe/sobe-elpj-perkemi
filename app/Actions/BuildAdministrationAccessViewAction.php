<?php

namespace App\Actions;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BuildAdministrationAccessViewAction
{
    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        $roles = Role::query()
            ->with(['permissions:id,name'])
            ->withCount('users')
            ->orderBy('name')
            ->get();
        $permissions = Permission::query()->orderBy('name')->get(['id', 'name']);
        $labels = [
            'activities' => 'Kegiatan',
            'action-center' => 'Action Center',
            'admin' => 'Administrasi',
            'ai' => 'Intelligence',
            'audit' => 'Audit',
            'budgets' => 'Anggaran',
            'dispositions' => 'Disposisi',
            'evidence' => 'Evidence',
            'finance' => 'Keuangan',
            'findings' => 'Temuan & CAPA',
            'hierarchy' => 'Hierarki',
            'integrations' => 'Integrasi',
            'lpjs' => 'e-LPJ',
            'mandates' => 'Mandat',
            'planning' => 'Planning',
        ];

        return [
            'summary' => [
                'roles' => $roles->count(),
                'permissions' => $permissions->count(),
                'assignments' => $roles->sum(fn (Role $role): int => $role->permissions->count()),
            ],
            'can_manage_roles' => $actor->can('admin.manage-roles'),
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'is_protected' => $role->name === 'Super Admin',
            ])->values(),
            'permission_groups' => $permissions
                ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
                ->map(fn ($items, string $domain): array => [
                    'domain' => $domain,
                    'label' => $labels[$domain] ?? str($domain)->headline()->toString(),
                    'permissions' => $items->pluck('name')->values(),
                ])
                ->values(),
        ];
    }
}
