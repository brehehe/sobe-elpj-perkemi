<?php

namespace Tests\Feature\Administration;

use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->configureHealthyBackup();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/administration')->assertRedirect('/login');
        $this->get('/administration/backup-health')->assertRedirect('/login');
    }

    public function test_user_without_administration_permission_is_forbidden(): void
    {
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());

        $this->actingAs($owner)->get('/administration')->assertForbidden();
        $this->actingAs($owner)->get('/administration/backup-health')->assertForbidden();
    }

    public function test_administrator_sees_real_access_policy_and_backup_summaries(): void
    {
        $admin = $this->userWithRole('Administrator', Unit::factory()->create());

        $this->actingAs($admin)->get('/administration')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/index')
                ->where('summary.users', 1)
                ->where('summary.active_users', 1)
                ->where('summary.units', 1)
                ->where('summary.active_units', 1)
                ->where('summary.roles', 11)
                ->where('summary.permissions', 41)
                ->has('policies', 4)
                ->where('policies.0.version', 'lpj-verification-v1')
                ->where('capabilities.0.href', '/administration/users')
                ->where('capabilities.1.href', '/administration/access')
                ->where('capabilities.6.title', 'Backup Health')
                ->where('capabilities.6.status', 'HEALTHY')
                ->where('capabilities.6.href', '/administration/backup-health'));
    }

    public function test_administrator_sees_sanitized_backup_health_contract(): void
    {
        $admin = $this->userWithRole('Administrator', Unit::factory()->create());

        $this->actingAs($admin)->get('/administration/backup-health')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/backup-health')
                ->where('status', 'HEALTHY')
                ->where('healthy', true)
                ->has('destinations', 1, fn (Assert $destination) => $destination
                    ->where('name', 'test-backup')
                    ->where('disk', 'backup-test')
                    ->where('reachable', true)
                    ->where('healthy', true)
                    ->where('backup_count', 1)
                    ->where('failures', [])
                    ->etc())
                ->where('restore_drill.status', 'CURRENT'));
    }

    public function test_legacy_administration_workspace_redirects_to_operational_page(): void
    {
        $admin = $this->userWithRole('Administrator', Unit::factory()->create());

        $this->actingAs($admin)
            ->get('/workspace/administration')
            ->assertRedirect('/administration');
    }

    private function configureHealthyBackup(): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        Storage::fake('backup-test');
        Storage::disk('backup-test')->put('test-backup/'.now()->format(BackupJob::FILENAME_FORMAT), 'encrypted-backup-placeholder');
        config([
            'backup.monitor_backups' => [[
                'name' => 'test-backup',
                'disks' => ['backup-test'],
                'health_checks' => [
                    MaximumAgeInDays::class => 1,
                    MaximumStorageInMegabytes::class => 100,
                ],
            ]],
            'operations.backup.last_restore_drill_at' => '2026-10-01 08:00:00',
            'operations.backup.restore_drill_maximum_age_days' => 90,
        ]);
        BackupConfig::rebind();

    }
}
