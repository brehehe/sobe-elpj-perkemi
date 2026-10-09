<?php

namespace App\Actions;

use App\Enums\GovernancePolicyType;
use App\Models\Unit;
use App\Models\User;
use App\Services\BackupHealthService;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BuildAdministrationViewAction
{
    public function __construct(
        private BackupHealthService $backupHealth,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        $backup = $this->backupHealth->snapshot();
        $users = User::query()->when(! $actor->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $actor->unit_id));
        $units = Unit::query()->when(! $actor->isGlobalAdministrator(), fn (Builder $query) => $query->whereKey($actor->unit_id));

        return [
            'summary' => [
                'users' => (clone $users)->count(),
                'active_users' => (clone $users)->where('is_active', true)->count(),
                'units' => (clone $units)->count(),
                'active_units' => (clone $units)->where('is_active', true)->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
            ],
            'policies' => [
                ['label' => 'Verifikasi LPJ', 'version' => $this->policyRegistry->get(GovernancePolicyType::Verification)['version'], 'scope' => count($this->policyRegistry->get(GovernancePolicyType::Verification)['stages'] ?? []).' tahap'],
                ['label' => 'Approval', 'version' => config('lpj.approval_policy.version'), 'scope' => count(config('lpj.approval_policy.levels', [])).' tingkat'],
                ['label' => 'Antrean workflow', 'version' => $this->policyRegistry->get(GovernancePolicyType::Workflow)['version'], 'scope' => 'SLA dan eskalasi aktif'],
                ['label' => 'Retensi arsip', 'version' => $this->policyRegistry->get(GovernancePolicyType::Retention)['version'], 'scope' => $this->policyRegistry->get(GovernancePolicyType::Retention)['retention_years'].' tahun'],
            ],
            'capabilities' => [
                ['title' => 'Pengguna', 'description' => 'Undang pengguna, atur unit dan role, serta kendalikan status akun.', 'status' => 'OPERATIONAL', 'href' => '/administration/users'],
                ['title' => 'Role & permission', 'description' => 'Tinjau matriks capability dan ubah permission sesuai kewenangan.', 'status' => 'OPERATIONAL', 'href' => '/administration/access'],
                ['title' => 'Unit organisasi', 'description' => 'Kelola struktur Pusat, Provinsi, Kota/Kabupaten, Dojo, dan batas kewenangan.', 'status' => 'OPERATIONAL', 'href' => '/administration/units'],
                ['title' => 'Taksonomi kegiatan', 'description' => 'Definisikan jenis kegiatan dan faktor pembanding untuk Planning serta Intelligence.', 'status' => 'OPERATIONAL', 'href' => $actor->can('admin.manage-master-data') ? '/administration/taxonomies' : null],
                ['title' => 'Definisi KPI', 'description' => 'Kelola indikator reusable, formula, satuan, bobot, dan cakupan jenis kegiatan.', 'status' => 'OPERATIONAL', 'href' => $actor->can('admin.manage-master-data') ? '/administration/kpis' : null],
                ['title' => 'Integration Hub', 'description' => 'Kesehatan provider, retry, dead-letter, dan pemilik operasional.', 'status' => 'OPERATIONAL', 'href' => '/integrations'],
                ['title' => 'Backup Health', 'description' => 'Keterjangkauan disk, usia backup, pemakaian, dan bukti restore drill.', 'status' => $backup['status'], 'href' => '/administration/backup-health'],
                ['title' => 'Audit & compliance', 'description' => 'Jejak perubahan append-only serta ekspor sesuai kewenangan.', 'status' => 'OPERATIONAL', 'href' => '/audit'],
                ['title' => 'Informasi sistem', 'description' => 'Versi, build, status layanan, klasifikasi data, dan lisensi.', 'status' => 'PUBLISHED', 'href' => '/about'],
                ['title' => 'Checklist verifikasi', 'description' => 'Publikasikan checklist domain dan SLA klarifikasi tanpa mengubah snapshot lama.', 'status' => 'VERSIONED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#verification' : null],
                ['title' => 'Workflow rules', 'description' => 'Kelola SLA gate, tenggat tugas, dan lead eskalasi secara terversi.', 'status' => 'VERSIONED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#workflow' : null],
                ['title' => 'Template LPJ', 'description' => 'Petakan taksonomi kegiatan ke template dan bagian laporan wajib.', 'status' => 'VERSIONED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#templates' : null],
                ['title' => 'Retention policies', 'description' => 'Atur masa simpan, skema paket, dan disk arsip untuk publikasi berikutnya.', 'status' => 'VERSIONED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#retention' : null],
                ['title' => 'AI settings & usage', 'description' => 'Kendalikan provider eksternal, anggaran token, ambang peringatan, dan lihat penggunaan.', 'status' => 'CONTROLLED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#ai' : null],
                ['title' => 'System settings', 'description' => 'Kelola klasifikasi data, pesan operasional, dan jalur bantuan pengguna.', 'status' => 'VERSIONED', 'href' => $actor->can('admin.manage-master-data') ? '/administration/governance#system' : null],
            ],
        ];
    }
}
