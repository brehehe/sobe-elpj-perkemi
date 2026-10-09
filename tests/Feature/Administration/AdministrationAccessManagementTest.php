<?php

namespace Tests\Feature\Administration;

use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AdministrationAccessManagementTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_administrator_can_inspect_but_cannot_change_role_matrix(): void
    {
        $administrator = $this->userWithRole('Administrator', Unit::factory()->create());
        $ownerRole = Role::findByName('Owner/PIC');

        $this->actingAs($administrator)->get('/administration/access')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/access')
                ->where('can_manage_roles', false)
                ->where('summary.roles', 11)
                ->where('summary.permissions', 41)
                ->has('roles', 11)
                ->where('roles', fn ($roles): bool => $roles->where('name', 'Super Admin')->first()['is_protected'])
                ->where('permission_groups', fn ($groups): bool => $groups->where('domain', 'admin')->isNotEmpty()));

        $this->actingAs($administrator)->put('/administration/access/roles/'.$ownerRole->id, [
            'permissions' => ['mandates.view'],
        ])->assertForbidden();
    }

    public function test_user_without_administration_permission_cannot_inspect_role_matrix(): void
    {
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());

        $this->actingAs($owner)->get('/administration/access')->assertForbidden();
    }

    public function test_super_admin_updates_role_permissions_and_change_is_audited(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());
        $ownerRole = Role::findByName('Owner/PIC');

        $this->actingAs($superAdmin)->put('/administration/access/roles/'.$ownerRole->id, [
            'permissions' => ['mandates.view', 'planning.view', 'activities.view'],
        ])->assertRedirect()->assertSessionHas('success');

        $ownerRole->refresh();
        $this->assertEqualsCanonicalizing(
            ['mandates.view', 'planning.view', 'activities.view'],
            $ownerRole->permissions()->pluck('name')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $superAdmin->id,
            'auditable_id' => (string) $ownerRole->id,
            'event_type' => 'role.permissions_updated',
            'authority' => 'admin.manage-roles',
        ]);
    }

    public function test_super_admin_role_is_immutable(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());
        $superAdminRole = Role::findByName('Super Admin');

        $this->actingAs($superAdmin)->from('/administration/access')->put('/administration/access/roles/'.$superAdminRole->id, [
            'permissions' => ['mandates.view'],
        ])->assertRedirect('/administration/access')->assertSessionHasErrors([
            'permissions' => 'Permission Super Admin mengikuti seluruh capability sistem dan tidak dapat diubah manual.',
        ]);

        $this->assertSame(41, $superAdminRole->fresh()->permissions()->count());
    }

    public function test_role_management_permission_cannot_be_delegated(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());
        $administratorRole = Role::findByName('Administrator');

        $this->actingAs($superAdmin)->from('/administration/access')->put('/administration/access/roles/'.$administratorRole->id, [
            'permissions' => ['admin.manage', 'admin.manage-roles'],
        ])->assertRedirect('/administration/access')->assertSessionHasErrors([
            'permissions' => 'Permission pengelolaan role hanya dimiliki Super Admin.',
        ]);

        $this->assertFalse($administratorRole->fresh()->hasPermissionTo('admin.manage-roles'));
    }

    public function test_permission_payload_must_be_a_valid_array(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());
        $ownerRole = Role::findByName('Owner/PIC');

        $this->actingAs($superAdmin)->from('/administration/access')->put('/administration/access/roles/'.$ownerRole->id, [
            'permissions' => 'mandates.view',
        ])->assertRedirect('/administration/access')->assertSessionHasErrors([
            'permissions' => 'Daftar permission tidak valid.',
        ]);
    }
}
