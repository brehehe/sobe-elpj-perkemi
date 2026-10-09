<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'mandates.view', 'mandates.manage',
            'planning.view', 'planning.manage', 'planning.review',
            'budgets.view', 'budgets.manage', 'budgets.review',
            'activities.view', 'activities.manage',
            'finance.reconcile', 'finance.resolve-exception', 'finance.approve-exception',
            'lpjs.view', 'lpjs.submit', 'lpjs.verify',
            'lpjs.verify.administration', 'lpjs.verify.finance', 'lpjs.verify.substantive',
            'findings.view', 'findings.manage', 'findings.respond',
            'dispositions.manage', 'dispositions.respond', 'action-center.view', 'hierarchy.view',
            'lpjs.approve', 'lpjs.pb_verify', 'lpjs.archive',
            'evidence.view', 'evidence.manage', 'evidence.review',
            'integrations.manage', 'ai.request', 'ai.review', 'audit.view',
            'admin.manage', 'admin.manage-users', 'admin.manage-roles', 'admin.manage-units', 'admin.manage-master-data',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Super Admin' => $permissions,
            'Owner/PIC' => ['mandates.view', 'planning.view', 'planning.manage', 'budgets.view', 'activities.view', 'activities.manage', 'lpjs.view', 'lpjs.submit', 'findings.view', 'findings.respond', 'dispositions.respond', 'action-center.view', 'evidence.view', 'evidence.manage', 'ai.request'],
            'Finance PIC' => ['mandates.view', 'planning.view', 'budgets.view', 'budgets.manage', 'activities.view', 'activities.manage', 'finance.reconcile', 'finance.resolve-exception', 'lpjs.view', 'lpjs.submit', 'findings.view', 'findings.respond', 'dispositions.respond', 'action-center.view', 'evidence.view', 'integrations.manage'],
            'Verifier Admin' => ['planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'lpjs.verify', 'lpjs.verify.administration', 'findings.view', 'findings.manage', 'dispositions.manage', 'action-center.view', 'evidence.view', 'evidence.review', 'ai.review'],
            'Verifier Finance' => ['planning.view', 'budgets.view', 'activities.view', 'finance.reconcile', 'finance.resolve-exception', 'lpjs.view', 'lpjs.verify', 'lpjs.verify.finance', 'findings.view', 'findings.manage', 'dispositions.manage', 'action-center.view', 'evidence.view', 'evidence.review', 'ai.review'],
            'Verifier Substantive' => ['planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'lpjs.verify', 'lpjs.verify.substantive', 'findings.view', 'findings.manage', 'dispositions.manage', 'action-center.view', 'evidence.view', 'evidence.review', 'ai.review'],
            'Approver' => ['planning.view', 'planning.review', 'budgets.view', 'budgets.review', 'activities.view', 'finance.approve-exception', 'lpjs.view', 'lpjs.approve', 'findings.view', 'dispositions.manage', 'action-center.view', 'evidence.view', 'ai.review'],
            'PB Verifier' => ['planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'lpjs.pb_verify', 'findings.view', 'dispositions.manage', 'action-center.view', 'hierarchy.view', 'evidence.view'],
            'Oversight' => ['mandates.view', 'planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'findings.view', 'action-center.view', 'hierarchy.view', 'evidence.view', 'ai.review', 'audit.view'],
            'Auditor' => ['mandates.view', 'planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'findings.view', 'action-center.view', 'hierarchy.view', 'evidence.view', 'audit.view'],
            'Administrator' => ['mandates.view', 'mandates.manage', 'planning.view', 'budgets.view', 'activities.view', 'lpjs.view', 'lpjs.archive', 'action-center.view', 'hierarchy.view', 'evidence.view', 'integrations.manage', 'audit.view', 'admin.manage', 'admin.manage-users', 'admin.manage-units'],
        ];
        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
