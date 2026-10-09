<?php

namespace Tests\Feature\SystemInformation;

use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class SystemInformationTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/about')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_system_information_without_admin_backup_data(): void
    {
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());

        $this->actingAs($owner)->get('/about')
            ->assertInertia(fn (Assert $page) => $page
                ->component('system/about')
                ->where('application.name', config('app.name'))
                ->where('application.backend_version', '0.1.0')
                ->where('application.frontend_version', '0.1.0')
                ->where('services.4.status', 'RESTRICTED')
                ->where('backup', null)
                ->has('release_notes', 3)
                ->has('shortcuts', 3)
                ->has('licenses', 6));
    }

    public function test_administrator_sees_backup_summary_but_not_sensitive_paths(): void
    {
        $this->configureHealthyBackup();
        $admin = $this->userWithRole('Administrator', Unit::factory()->create());

        $response = $this->actingAs($admin)->get('/about');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('system/about')
            ->where('backup.status', 'HEALTHY')
            ->where('backup.restore_drill.status', 'CURRENT')
            ->where('backup.href', '/administration/backup-health')
            ->missing('backup.destinations'));
        $this->assertStringNotContainsString('test-backup/', $response->getContent());
    }

    public function test_unsafe_contact_and_external_link_configuration_is_not_exposed(): void
    {
        config([
            'operations.contact.support_email' => 'not-an-email',
            'operations.contact.security_email' => "security@example.test\r\nBcc: attacker@example.test",
            'operations.links.documentation' => 'javascript:alert(1)',
            'operations.links.privacy' => 'http://insecure.example.test',
        ]);
        $owner = $this->userWithRole('Owner/PIC', Unit::factory()->create());

        $this->actingAs($owner)->get('/about')
            ->assertInertia(fn (Assert $page) => $page
                ->where('governance.support_email', null)
                ->where('governance.security_email', null)
                ->where('governance.documentation_url', null)
                ->where('governance.privacy_url', null));
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
                'health_checks' => [MaximumAgeInDays::class => 1],
            ]],
            'operations.backup.last_restore_drill_at' => '2026-10-01 08:00:00',
        ]);
        BackupConfig::rebind();

    }
}
