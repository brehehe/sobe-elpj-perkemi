<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateRolePermissionsAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param list<string> $permissionNames */
    public function handle(Role $role, User $actor, array $permissionNames): Role
    {
        return DB::transaction(function () use ($role, $actor, $permissionNames): Role {
            $managedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            if ($managedRole->name === 'Super Admin') {
                throw ValidationException::withMessages([
                    'permissions' => 'Permission Super Admin mengikuti seluruh capability sistem dan tidak dapat diubah manual.',
                ]);
            }

            if (in_array('admin.manage-roles', $permissionNames, true)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Permission pengelolaan role hanya dimiliki Super Admin.',
                ]);
            }

            $before = $managedRole->permissions()->pluck('name')->sort()->values()->all();
            $after = collect($permissionNames)->sort()->values()->all();
            $managedRole->syncPermissions($after);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->recordAuditEvent->handle(
                $managedRole,
                'role.permissions_updated',
                $actor,
                payload: [
                    'role' => $managedRole->name,
                    'before_permissions' => $before,
                    'after_permissions' => $after,
                ],
                authority: 'admin.manage-roles',
            );

            return $managedRole->fresh('permissions');
        });
    }
}
