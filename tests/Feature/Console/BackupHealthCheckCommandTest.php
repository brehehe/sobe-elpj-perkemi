<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Tests\TestCase;

class BackupHealthCheckCommandTest extends TestCase
{
    public function test_returns_success_for_current_backup_and_restore_drill(): void
    {
        $this->configureBackup(hasBackup: true, restoreDrillAt: '2026-10-01 08:00:00');

        $this->artisan('lpj:backup-health')
            ->expectsOutputToContain('Backup health: HEALTHY')
            ->expectsOutputToContain('Restore drill: CURRENT')
            ->assertSuccessful();
    }

    public function test_returns_failure_when_restore_drill_has_not_been_recorded(): void
    {
        $this->configureBackup(hasBackup: true, restoreDrillAt: null);

        $this->artisan('lpj:backup-health')
            ->expectsOutputToContain('Backup health: ATTENTION')
            ->expectsOutputToContain('Restore drill: NOT_RECORDED')
            ->expectsOutputToContain('Backup belum memenuhi seluruh kontrak kesehatan dan pemulihan.')
            ->assertFailed();
    }

    public function test_json_output_fails_when_no_backup_exists_and_exposes_no_storage_path(): void
    {
        $this->configureBackup(hasBackup: false, restoreDrillAt: '2026-10-01 08:00:00');

        $pending = $this->artisan('lpj:backup-health', ['--json' => true]);

        $pending->expectsOutputToContain('"status":"UNHEALTHY"')
            ->doesntExpectOutputToContain(storage_path())
            ->assertFailed();
    }

    private function configureBackup(bool $hasBackup, ?string $restoreDrillAt): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        Storage::fake('backup-test');
        if ($hasBackup) {
            Storage::disk('backup-test')->put('test-backup/'.now()->format(BackupJob::FILENAME_FORMAT), 'encrypted-backup-placeholder');
        }
        config([
            'backup.monitor_backups' => [[
                'name' => 'test-backup',
                'disks' => ['backup-test'],
                'health_checks' => [MaximumAgeInDays::class => 1],
            ]],
            'operations.backup.last_restore_drill_at' => $restoreDrillAt,
            'operations.backup.restore_drill_maximum_age_days' => 90,
        ]);
        BackupConfig::rebind();
    }
}
