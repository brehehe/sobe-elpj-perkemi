<?php

namespace App\Http\Controllers;

use App\Actions\PublishGovernancePolicyAction;
use App\Enums\GovernancePolicyType;
use App\Http\Requests\PublishGovernancePolicyRequest;
use App\Models\GovernancePolicy;
use App\Models\User;
use App\Services\AiUsageGuard;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationGovernanceController extends Controller
{
    public function index(GovernancePolicyRegistry $registry, AiUsageGuard $aiUsage): Response
    {
        $this->authorize('viewAny', GovernancePolicy::class);
        $history = GovernancePolicy::query()
            ->with('creator:id,name')
            ->latest('activated_at')
            ->get()
            ->groupBy(fn (GovernancePolicy $policy): string => $policy->type->value);

        return Inertia::render('administration/governance', [
            'policies' => collect(GovernancePolicyType::cases())->map(function (GovernancePolicyType $type) use ($registry, $history): array {
                $active = $registry->get($type);

                return [
                    'type' => $type->value,
                    'anchor' => mb_strtolower($type->value),
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'active_version' => $active['version'] ?? 'unversioned',
                    'source' => ($history->get($type->value)?->firstWhere('is_active', true)) === null ? 'CONFIG_DEFAULT' : 'PUBLISHED',
                    'fields' => $this->fields($type, $active),
                    'history' => ($history->get($type->value) ?? collect())->take(6)->map(fn (GovernancePolicy $policy): array => [
                        'id' => $policy->id,
                        'version' => $policy->version,
                        'change_summary' => $policy->change_summary,
                        'is_active' => $policy->is_active,
                        'creator' => $policy->creator?->name ?? 'System',
                        'activated_at' => $policy->activated_at?->toIso8601String(),
                    ])->values(),
                ];
            })->values(),
            'aiUsage' => [
                ...$aiUsage->status(),
                'provider_configured' => (bool) config('services.openai.enabled') && filled(config('services.openai.key')),
                'model' => (string) config('services.openai.model'),
            ],
        ]);
    }

    public function store(PublishGovernancePolicyRequest $request, string $type, PublishGovernancePolicyAction $publish): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $policyType = $request->policyType();
        abort_if($policyType === null, 404);

        $publish->handle(
            $policyType,
            $actor,
            $request->string('version')->toString(),
            $request->string('change_summary')->trim()->toString(),
            $request->policySettings(),
        );

        return back()->with('success', $policyType->label().' versi baru berhasil dipublikasikan. Snapshot historis tetap utuh.');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function fields(GovernancePolicyType $type, array $settings): array
    {
        return match ($type) {
            GovernancePolicyType::Verification => [
                $this->field('clarification_due_days', 'Batas klarifikasi (hari)', 'number', $settings['clarification_due_days'] ?? 3, '1–30 hari.', 1, 30),
                $this->field('administration_checklist', 'Checklist administrasi', 'textarea', implode("\n", data_get($settings, 'stages.ADMINISTRATION.checklist', [])), 'Satu butir per baris.'),
                $this->field('finance_checklist', 'Checklist keuangan', 'textarea', implode("\n", data_get($settings, 'stages.FINANCE.checklist', [])), 'Satu butir per baris.'),
                $this->field('substantive_checklist', 'Checklist substantif', 'textarea', implode("\n", data_get($settings, 'stages.SUBSTANTIVE.checklist', [])), 'Satu butir per baris.'),
            ],
            GovernancePolicyType::Workflow => [
                $this->field('verification_due_days', 'SLA verifikasi (hari)', 'number', $settings['verification_due_days'] ?? 3, null, 1, 30),
                $this->field('approval_due_days', 'SLA approval (hari)', 'number', $settings['approval_due_days'] ?? 2, null, 1, 30),
                $this->field('pb_verification_due_days', 'SLA verifikasi PB (hari)', 'number', $settings['pb_verification_due_days'] ?? 2, null, 1, 30),
                $this->field('archive_due_days', 'SLA arsip (hari)', 'number', $settings['archive_due_days'] ?? 2, null, 1, 30),
                $this->field('revision_due_days', 'SLA revisi (hari)', 'number', $settings['revision_due_days'] ?? 3, null, 1, 30),
                $this->field('escalation_lead_hours', 'Lead eskalasi (jam)', 'number', $settings['escalation_lead_hours'] ?? 12, 'Dihitung mundur dari tenggat.', 1, 168),
            ],
            GovernancePolicyType::Templates => [
                $this->field('default_label', 'Template default', 'text', $settings['default_label'] ?? 'Kegiatan umum'),
                $this->field('template_mappings', 'Pemetaan template', 'textarea', collect($settings['mappings'] ?? [])->map(fn (array $mapping): string => $mapping['activity_type'].'='.$mapping['label'])->implode("\n"), 'Format KODE=Nama template, satu per baris. Kode harus berasal dari taksonomi aktif.'),
                $this->field('sections', 'Bagian wajib', 'textarea', implode("\n", $settings['sections'] ?? []), 'Tiga sampai dua puluh bagian, satu per baris.'),
            ],
            GovernancePolicyType::Retention => [
                $this->field('retention_years', 'Masa simpan (tahun)', 'number', $settings['retention_years'] ?? 10, null, 1, 50),
                $this->field('schema_version', 'Versi skema paket', 'number', $settings['schema_version'] ?? 1, null, 1, 100),
                $this->field('disk', 'Disk arsip', 'select', $settings['disk'] ?? 'local', 'Hanya disk yang dikonfigurasi server.', options: collect(array_keys(config('filesystems.disks', [])))->map(fn (string $disk): array => ['value' => $disk, 'label' => $disk])->all()),
            ],
            GovernancePolicyType::Ai => [
                $this->field('external_provider_enabled', 'Izinkan provider AI eksternal', 'checkbox', (bool) ($settings['external_provider_enabled'] ?? false), 'Jika nonaktif, pemeriksaan lokal tetap tersedia.'),
                $this->field('daily_token_budget', 'Anggaran token harian', 'number', $settings['daily_token_budget'] ?? 0, 'Nilai 0 berarti tanpa batas harian aplikasi.', 0, 10000000),
                $this->field('monthly_token_budget', 'Anggaran token bulanan', 'number', $settings['monthly_token_budget'] ?? 0, 'Nilai 0 berarti tanpa batas bulanan aplikasi.', 0, 300000000),
                $this->field('warning_percent', 'Ambang peringatan (%)', 'number', $settings['warning_percent'] ?? 80, null, 1, 99),
                $this->field('user_rate_per_minute', 'Batas run per pengguna / menit', 'number', $settings['user_rate_per_minute'] ?? 10, null, 1, 100),
                $this->field('unit_rate_per_minute', 'Batas run per unit / menit', 'number', $settings['unit_rate_per_minute'] ?? 30, null, 1, 1000),
                $this->field('organization_rate_per_minute', 'Batas run organisasi / menit', 'number', $settings['organization_rate_per_minute'] ?? 120, null, 1, 10000),
                $this->field('review_disclaimer', 'Disclaimer review', 'textarea', $settings['review_disclaimer'] ?? 'Hasil AI wajib ditinjau manusia.'),
            ],
            GovernancePolicyType::System => [
                $this->field('data_classification', 'Klasifikasi data', 'text', $settings['data_classification'] ?? 'Internal PB PERKEMI'),
                $this->field('maintenance_message', 'Pesan operasional', 'textarea', $settings['maintenance_message'] ?? '', 'Kosongkan untuk menyembunyikan banner.'),
                $this->field('support_message', 'Pesan bantuan', 'textarea', $settings['support_message'] ?? 'Hubungi administrator sistem.'),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function field(string $name, string $label, string $type, mixed $value, ?string $help = null, ?int $min = null, ?int $max = null, array $options = []): array
    {
        return compact('name', 'label', 'type', 'value', 'help', 'min', 'max', 'options');
    }
}
