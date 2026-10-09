<?php

namespace App\Console\Commands;

use App\Services\BackupHealthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lpj:backup-health {--json : Output machine-readable JSON}')]
#[Description('Check backup destinations and restore-drill evidence without modifying backup data')]
class BackupHealthCheckCommand extends Command
{
    public function handle(BackupHealthService $backupHealth): int
    {
        $snapshot = $backupHealth->snapshot();

        if ($this->option('json')) {
            $this->line((string) json_encode($snapshot, JSON_THROW_ON_ERROR));

            return $snapshot['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('Backup health: '.$snapshot['status']);
        $this->table(
            ['Name', 'Disk', 'Status', 'Backups', 'Newest backup', 'Used bytes'],
            collect($snapshot['destinations'])->map(fn (array $destination): array => [
                $destination['name'],
                $destination['disk'],
                $destination['status'],
                $destination['backup_count'],
                $destination['newest_backup_at'] ?? 'Belum tersedia',
                $destination['used_storage_bytes'] ?? 'Tidak tersedia',
            ])->all(),
        );
        $this->line('Restore drill: '.$snapshot['restore_drill']['status']);
        $this->line($snapshot['restore_drill']['guidance']);

        if (! $snapshot['healthy']) {
            $this->components->error('Backup belum memenuhi seluruh kontrak kesehatan dan pemulihan.');
        }

        return $snapshot['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
