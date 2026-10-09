<?php

namespace App\Actions;

use App\Enums\GovernancePolicyType;
use App\Enums\IntegrationStatus;
use App\Models\IntegrationRun;
use App\Models\User;
use App\Services\BackupHealthService;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BuildSystemInformationViewAction
{
    public function __construct(
        private BackupHealthService $backupHealth,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $backup = $user->can('admin.manage') ? $this->backupHealth->snapshot() : null;
        $systemPolicy = $this->policyRegistry->get(GovernancePolicyType::System);

        return [
            'application' => [
                'name' => config('app.name'),
                'purpose' => config('operations.application.purpose'),
                'backend_version' => config('operations.release.backend_version'),
                'frontend_version' => config('operations.release.frontend_version'),
                'build_id' => config('operations.release.build_id'),
                'released_at' => config('operations.release.released_at'),
                'environment' => config('operations.environment_label'),
            ],
            'services' => [
                ['name' => 'Aplikasi web', 'status' => 'OPERATIONAL', 'detail' => 'Request autentikasi dan Inertia aktif.'],
                ['name' => 'Database', 'status' => $this->databaseStatus(), 'detail' => 'Koneksi '.config('database.default').' tanpa menampilkan host atau kredensial.'],
                ['name' => 'Cache', 'status' => $this->cacheStatus(), 'detail' => 'Store '.config('cache.default').' untuk state sementara dan lock.'],
                ['name' => 'Queue', 'status' => 'CONFIGURED', 'detail' => 'Connection '.config('queue.default').'; kesehatan worker dipantau di sisi operasional.'],
                $this->integrationStatus($user),
            ],
            'release_notes' => config('operations.release.notes', []),
            'governance' => [
                'classification' => $systemPolicy['data_classification'] ?? config('operations.governance.classification'),
                'policy_version' => $systemPolicy['version'],
                'support_message' => $systemPolicy['support_message'] ?? null,
                'privacy' => config('operations.governance.privacy'),
                'accessibility' => config('operations.governance.accessibility'),
                'owner' => config('operations.contact.owner'),
                'support_email' => $this->safeEmail(config('operations.contact.support_email')),
                'security_email' => $this->safeEmail(config('operations.contact.security_email')),
                'documentation_url' => $this->safeUrl(config('operations.links.documentation')),
                'privacy_url' => $this->safeUrl(config('operations.links.privacy')),
            ],
            'shortcuts' => [
                ['keys' => 'Ctrl/Cmd + K', 'action' => 'Buka pencarian global'],
                ['keys' => 'Esc', 'action' => 'Tutup dialog atau menu aktif'],
                ['keys' => 'Tab / Shift + Tab', 'action' => 'Pindah antar kontrol interaktif'],
            ],
            'licenses' => config('operations.licenses', []),
            'backup' => $backup === null ? null : [
                'status' => $backup['status'],
                'newest_backup_at' => $backup['newest_backup_at'],
                'restore_drill' => $backup['restore_drill'],
                'href' => '/administration/backup-health',
            ],
        ];
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'OPERATIONAL';
        } catch (Throwable) {
            return 'DEGRADED';
        }
    }

    private function cacheStatus(): string
    {
        try {
            Cache::get('system-information-health-probe');

            return 'OPERATIONAL';
        } catch (Throwable) {
            return 'DEGRADED';
        }
    }

    /** @return array{name: string, status: string, detail: string, href?: string} */
    private function integrationStatus(User $user): array
    {
        if (! $user->can('integrations.manage')) {
            return ['name' => 'Integrasi', 'status' => 'RESTRICTED', 'detail' => 'Detail kesehatan dibatasi kepada pengelola integrasi.'];
        }

        $latest = IntegrationRun::query()
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
            ->latest('created_at')
            ->first(['status']);

        $status = match (true) {
            $latest === null => 'NOT_OBSERVED',
            in_array($latest->status, [IntegrationStatus::Failed, IntegrationStatus::PartialFailed, IntegrationStatus::DeadLetter], true) => 'DEGRADED',
            default => 'OPERATIONAL',
        };

        return ['name' => 'Integrasi', 'status' => $status, 'detail' => 'Status run terbaru sesuai scope unit.', 'href' => '/integrations'];
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) && str_starts_with($value, 'https://') ? $value : null;
    }

    private function safeEmail(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
