<?php

namespace Tests\Feature\Administration;

use App\Models\AuditEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AdministrationUserManagementTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_and_user_without_permission_cannot_open_user_administration(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);

        $this->get('/administration/users')->assertRedirect('/login');
        $this->actingAs($owner)->get('/administration/users')->assertForbidden();
    }

    public function test_local_administrator_sees_only_users_from_their_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'SOBE Jawa Barat']);
        $otherUnit = Unit::factory()->create(['name' => 'SOBE Rahasia']);
        $administrator = $this->userWithRole('Administrator', $unit);
        $visible = $this->userWithRole('Finance PIC', $unit);
        $hidden = $this->userWithRole('Finance PIC', $otherUnit);

        $this->actingAs($administrator)->get('/administration/users')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/users')
                ->where('scope.can_view_all_units', false)
                ->where('scope.label', 'SOBE Jawa Barat')
                ->where('summary.total', 2)
                ->has('users.data', 2)
                ->where('users.data.0.id', fn (string $id): bool => in_array($id, [$administrator->id, $visible->id], true))
                ->where('users.data', fn ($users): bool => $users->where('id', $hidden->id)->isEmpty())
                ->has('options.units', 1)
                ->where('options.roles', fn ($roles): bool => $roles->where('name', 'Super Admin')->isEmpty()));
    }

    public function test_local_administrator_cannot_probe_another_unit_filter_or_user(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);
        $otherUser = $this->userWithRole('Owner/PIC', $otherUnit);

        $this->actingAs($administrator)->get('/administration/users?unit='.$otherUnit->id)->assertNotFound();
        $this->actingAs($administrator)->patch('/administration/users/'.$otherUser->id, [
            'name' => 'Tidak boleh',
            'email' => 'blocked@perkemi.test',
            'unit_id' => $otherUnit->id,
            'role' => 'Owner/PIC',
            'is_active' => true,
        ])->assertNotFound();

        $this->assertNotSame('Tidak boleh', $otherUser->fresh()->name);
    }

    public function test_super_admin_can_filter_users_across_units(): void
    {
        $firstUnit = Unit::factory()->create();
        $secondUnit = Unit::factory()->create(['name' => 'SOBE Bali']);
        $superAdmin = $this->userWithRole('Super Admin', $firstUnit);
        $visible = $this->userWithRole('Auditor', $secondUnit);

        $this->actingAs($superAdmin)->get('/administration/users?unit='.$secondUnit->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_all_units', true)
                ->where('filters.unit', $secondUnit->id)
                ->where('summary.total', 1)
                ->has('users.data', 1)
                ->where('users.data.0.id', $visible->id)
                ->has('options.units', 2));
    }

    public function test_administrator_creates_scoped_user_with_secure_invitation_and_audit_event(): void
    {
        Notification::fake();
        $unit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);

        $response = $this->actingAs($administrator)->post('/administration/users', [
            'name' => '  Nanda   Wijaya  ',
            'email' => 'NANDA@PERKEMI.TEST',
            'unit_id' => $unit->id,
            'role' => 'Verifier Admin',
            'is_active' => true,
            'password' => 'must-not-be-accepted',
        ]);

        $response->assertRedirect('/administration/users')->assertSessionHas('success');
        $created = User::query()->where('email', 'nanda@perkemi.test')->firstOrFail();
        $this->assertSame('Nanda Wijaya', $created->name);
        $this->assertSame($unit->id, $created->unit_id);
        $this->assertTrue($created->is_active);
        $this->assertNull($created->email_verified_at);
        $this->assertTrue($created->hasRole('Verifier Admin'));
        $this->assertFalse(password_verify('must-not-be-accepted', $created->password));
        Notification::assertSentTo($created, VerifyEmail::class);
        Notification::assertSentTo($created, ResetPassword::class);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $administrator->id,
            'auditable_id' => $created->id,
            'event_type' => 'user.created',
            'to_status' => 'ACTIVE',
            'authority' => 'admin.manage-users',
        ]);
    }

    public function test_local_administrator_cannot_create_global_or_cross_unit_account(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);

        $this->actingAs($administrator)->from('/administration/users')->post('/administration/users', [
            'name' => 'Global palsu',
            'email' => 'global@perkemi.test',
            'unit_id' => $otherUnit->id,
            'role' => 'Super Admin',
            'is_active' => true,
        ])->assertRedirect('/administration/users')->assertSessionHasErrors([
            'unit_id' => 'Administrator unit hanya dapat mengelola unitnya sendiri.',
            'role' => 'Role Super Admin hanya dapat diberikan oleh Super Admin.',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'global@perkemi.test']);
    }

    public function test_administrator_updates_user_access_and_records_before_after_values(): void
    {
        $unit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);
        $managedUser = $this->userWithRole('Owner/PIC', $unit);

        $this->actingAs($administrator)->patch('/administration/users/'.$managedUser->id, [
            'name' => 'Pengguna Diperbarui',
            'email' => $managedUser->email,
            'unit_id' => $unit->id,
            'role' => 'Auditor',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHas('success');

        $managedUser->refresh();
        $this->assertSame('Pengguna Diperbarui', $managedUser->name);
        $this->assertFalse($managedUser->is_active);
        $this->assertTrue($managedUser->hasRole('Auditor'));
        $event = $managedUser->morphMany(AuditEvent::class, 'auditable')->latest()->firstOrFail();
        $this->assertSame('user.access_updated', $event->event_type);
        $this->assertSame('Owner/PIC', $event->payload['before']['role']);
        $this->assertSame('Auditor', $event->payload['after']['role']);
        $this->assertSame('INACTIVE', $event->to_status);
    }

    public function test_current_account_cannot_change_its_role_or_deactivate_itself(): void
    {
        $unit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);

        $this->actingAs($administrator)->from('/administration/users')->patch('/administration/users/'.$administrator->id, [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'unit_id' => $unit->id,
            'role' => 'Owner/PIC',
            'is_active' => false,
        ])->assertRedirect('/administration/users')->assertSessionHasErrors([
            'role' => 'Role atau status akun yang sedang digunakan tidak dapat diubah dari sesi ini.',
        ]);

        $administrator->refresh();
        $this->assertTrue($administrator->is_active);
        $this->assertTrue($administrator->hasRole('Administrator'));
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $firstUnit = Unit::factory()->create();
        $secondUnit = Unit::factory()->create();
        $inactiveActor = $this->userWithRole('Super Admin', $firstUnit);
        $inactiveActor->update(['is_active' => false]);
        $lastActive = $this->userWithRole('Super Admin', $secondUnit);

        $this->actingAs($inactiveActor)->from('/administration/users')->patch('/administration/users/'.$lastActive->id, [
            'name' => $lastActive->name,
            'email' => $lastActive->email,
            'unit_id' => $secondUnit->id,
            'role' => 'Super Admin',
            'is_active' => false,
        ])->assertRedirect('/administration/users')->assertSessionHasErrors([
            'role' => 'Minimal satu Super Admin aktif harus tetap tersedia.',
        ]);

        $this->assertTrue($lastActive->fresh()->is_active);
    }
}
