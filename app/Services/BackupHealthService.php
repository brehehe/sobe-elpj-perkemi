<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Spatie\Backup\Tasks\Monitor\HealthCheckFailure;
use Spatie\Backup\Tasks\Monitor\HealthChecks\IsReachable;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Throwable;

class BackupHealthService
{
    public function __construct(private BackupConfig $backupConfig) {}

    /**
     * @return array{
     *     status: string,
     *     healthy: bool,
     *     checked_at: string,
     *     newest_backup_at: string|null,
     *     destinations: array<int, array<string, mixed>>,
     *     restore_drill: array{status: string, last_verified_at: string|null, maximum_age_days: int, guidance: string}
     * }
     */
    public function snapshot(): array
    {
        $destinations = BackupDestinationStatusFactory::createForMonitorConfig($this->backupConfig->monitoredBackups)
            ->map(function ($destinationStatus): array {
                $destination = $destinationStatus->backupDestination();
                $reachable = $destination->isReachable();
                $healthy = $destinationStatus->isHealthy();
                $newestBackup = $reachable ? $destination->newestBackup() : null;

                return [
                    'name' => $destination->backupName(),
                    'disk' => $destination->diskName(),
                    'storage_type' => $destination->filesystemType(),
                    'reachable' => $reachable,
                    'healthy' => $healthy,
                    'status' => ! $reachable ? 'UNREACHABLE' : ($healthy ? 'HEALTHY' : 'UNHEALTHY'),
                    'backup_count' => $reachable ? $destination->backups()->count() : 0,
                    'newest_backup_at' => $newestBackup?->date()->setTimezone(config('app.timezone'))->toIso8601String(),
                    'age_hours' => $newestBackup ? (int) floor($newestBackup->date()->diffInHours(now(), true)) : null,
                    'used_storage_bytes' => $reachable ? (int) $destination->usedStorage() : null,
                    'failures' => $destinationStatus->getHealthCheckFailures()
                        ->map(fn (HealthCheckFailure $failure): array => [
                            'check' => $failure->healthCheck()->name(),
                            'guidance' => $this->failureGuidance($failure),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $restoreDrill = $this->restoreDrillStatus();
        $allDestinationsHealthy = $destinations !== []
            && collect($destinations)->every(fn (array $destination): bool => $destination['healthy']);
        $status = match (true) {
            ! $allDestinationsHealthy => 'UNHEALTHY',
            $restoreDrill['status'] !== 'CURRENT' => 'ATTENTION',
            default => 'HEALTHY',
        };

        return [
            'status' => $status,
            'healthy' => $status === 'HEALTHY',
            'checked_at' => now()->toIso8601String(),
            'newest_backup_at' => collect($destinations)->pluck('newest_backup_at')->filter()->sortDesc()->first(),
            'destinations' => $destinations,
            'restore_drill' => $restoreDrill,
        ];
    }

    /** @return array{status: string, last_verified_at: string|null, maximum_age_days: int, guidance: string} */
    private function restoreDrillStatus(): array
    {
        $maximumAgeDays = (int) config('operations.backup.restore_drill_maximum_age_days', 90);
        $configuredDate = config('operations.backup.last_restore_drill_at');
        $verifiedAt = null;

        if (is_string($configuredDate) && $configuredDate !== '') {
            try {
                $verifiedAt = CarbonImmutable::parse($configuredDate, config('app.timezone'));
            } catch (Throwable) {
                $verifiedAt = null;
            }
        }

        $status = match (true) {
            $verifiedAt === null => 'NOT_RECORDED',
            $verifiedAt->isBefore(now()->subDays($maximumAgeDays)) => 'STALE',
            default => 'CURRENT',
        };

        return [
            'status' => $status,
            'last_verified_at' => $verifiedAt?->toIso8601String(),
            'maximum_age_days' => $maximumAgeDays,
            'guidance' => match ($status) {
                'CURRENT' => 'Bukti restore drill staging masih berada dalam periode kebijakan.',
                'STALE' => 'Jalankan restore drill pada staging dan perbarui tanggal bukti operasional.',
                default => 'Belum ada bukti restore drill staging. Backup belum boleh dianggap siap dipulihkan.',
            },
        ];
    }

    private function failureGuidance(HealthCheckFailure $failure): string
    {
        return match (true) {
            $failure->healthCheck() instanceof IsReachable => 'Periksa koneksi, kredensial, dan izin tulis/baca pada disk backup.',
            $failure->healthCheck() instanceof MaximumAgeInDays => 'Jalankan backup terjadwal dan periksa worker scheduler bila backup terbaru sudah terlalu lama.',
            $failure->healthCheck() instanceof MaximumStorageInMegabytes => 'Tinjau kebijakan cleanup dan kapasitas penyimpanan sebelum backup berikutnya.',
            default => 'Tinjau log server untuk detail pemeriksaan tanpa mengekspos konfigurasi sensitif ke antarmuka.',
        };
    }
}
