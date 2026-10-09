<?php

namespace Tests\Feature\Administration;

use App\Actions\RequestAiReviewAction;
use App\Actions\SubmitLpjAction;
use App\Actions\SynchronizeLpjWorkflowTasksAction;
use App\Enums\GovernancePolicyType;
use App\Models\GovernancePolicy;
use App\Models\SmartTask;
use App\Models\Unit;
use App\Services\GovernancePolicyRegistry;
use App\Services\LpjSubmissionGate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class GovernancePolicyManagementTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_only_global_master_data_administrator_can_manage_governance_policies(): void
    {
        $unit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);
        $superAdmin = $this->userWithRole('Super Admin', $unit);

        $this->get('/administration/governance')->assertRedirect('/login');
        $this->actingAs($administrator)->get('/administration/governance')->assertForbidden();
        $this->actingAs($administrator)->post('/administration/governance/SYSTEM', $this->systemPayload())->assertForbidden();

        $this->actingAs($superAdmin)->get('/administration/governance')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/governance')
                ->has('policies', 6)
                ->where('policies.0.active_version', 'lpj-verification-v1')
                ->where('policies.5.active_version', 'lpj-system-governance-v1')
                ->has('aiUsage.provider_configured'));
    }

    public function test_publishing_a_policy_activates_one_version_and_records_an_audit_event(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());

        $this->actingAs($superAdmin)->post('/administration/governance/VERIFICATION', $this->verificationPayload('lpj-verification-v2', 'Checklist versi kedua untuk alur baru.'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($superAdmin)->post('/administration/governance/VERIFICATION', $this->verificationPayload('lpj-verification-v3', 'Checklist versi ketiga untuk alur baru.'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('governance_policies', ['type' => 'VERIFICATION', 'version' => 'lpj-verification-v2', 'is_active' => false]);
        $this->assertDatabaseHas('governance_policies', ['type' => 'VERIFICATION', 'version' => 'lpj-verification-v3', 'is_active' => true]);
        $this->assertSame(1, GovernancePolicy::query()->where('type', GovernancePolicyType::Verification)->where('is_active', true)->count());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $superAdmin->id,
            'event_type' => 'governance_policy.published',
            'authority' => 'admin.manage-master-data',
            'object_version' => 'lpj-verification-v3',
        ]);

        $this->actingAs($superAdmin)->from('/administration/governance')->post('/administration/governance/VERIFICATION', $this->verificationPayload('lpj-verification-v3', 'Versi duplikat harus ditolak.'))
            ->assertRedirect('/administration/governance')
            ->assertSessionHasErrors('version');
    }

    public function test_new_verification_policy_is_snapshotted_without_mutating_an_existing_lpj_version(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);
        $firstWorkflow = $this->completeWorkflow($unit);

        $this->actingAs($superAdmin)->post('/administration/governance/VERIFICATION', $this->verificationPayload('lpj-verification-v2', 'Checklist baru untuk pengajuan setelah publikasi.'));
        $firstVersion = app(SubmitLpjAction::class)->handle($firstWorkflow['lpj'], $firstWorkflow['owner']);
        $firstSnapshot = $firstVersion->snapshot;

        $this->actingAs($superAdmin)->post('/administration/governance/VERIFICATION', $this->verificationPayload('lpj-verification-v3', 'Checklist lanjutan untuk pengajuan berikutnya.', 'Administrasi versi tiga'));
        $secondWorkflow = $this->completeWorkflow($unit);
        $secondVersion = app(SubmitLpjAction::class)->handle($secondWorkflow['lpj'], $secondWorkflow['owner']);

        $this->assertSame('lpj-verification-v2', data_get($firstVersion->fresh()->snapshot, 'verification.policy_version'));
        $this->assertSame($firstSnapshot, $firstVersion->fresh()->snapshot);
        $this->assertSame('lpj-verification-v3', data_get($secondVersion->snapshot, 'verification.policy_version'));
        $this->assertSame('Administrasi versi tiga', data_get($secondVersion->snapshot, 'verification.stages.ADMINISTRATION.checklist.0'));
    }

    public function test_template_and_system_policies_affect_live_views_without_exposing_secrets(): void
    {
        config()->set('services.openai.key', 'secret-key-must-not-appear');
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);
        $workflow = $this->completeWorkflow($unit);
        $workflow['plan']->currentVersionRecord()->update(['activity_type' => 'PEMBINAAN']);

        $this->actingAs($superAdmin)->post('/administration/governance/TEMPLATES', [
            'version' => 'lpj-template-v2',
            'change_summary' => 'Nama template pembinaan diperjelas untuk laporan baru.',
            'default_label' => 'Kegiatan umum resmi',
            'template_mappings' => "PENATARAN=Penataran resmi\nKEJUARAAN=Kejuaraan resmi\nRAPAT=Rapat resmi\nPEMBINAAN=Template pembinaan nasional\nUJIAN=Ujian resmi\nLAINNYA=Kegiatan umum resmi",
            'sections' => "Identitas\nPelaksanaan\nKeuangan\nEvidence\nEvaluasi",
        ])->assertSessionHasNoErrors();
        $this->actingAs($superAdmin)->post('/administration/governance/SYSTEM', $this->systemPayload())
            ->assertSessionHasNoErrors();

        $precheck = app(LpjSubmissionGate::class)->evaluate($workflow['lpj']);
        $this->assertSame('Template pembinaan nasional', $precheck['template']['label']);
        $this->assertSame('lpj-template-v2', $precheck['template']['policy_version']);

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('system.policy_version', 'lpj-system-governance-v2')
                ->where('system.data_classification', 'Internal terbatas')
                ->where('system.maintenance_message', 'Pemeliharaan terjadwal Sabtu pukul 22.00 WIB.'));

        $this->actingAs($superAdmin)->get('/administration/governance')
            ->assertDontSee('secret-key-must-not-appear');
    }

    public function test_workflow_policy_applies_to_new_tasks_without_rewriting_an_active_task_contract(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);
        $workflow = $this->completeWorkflow($unit);

        $this->actingAs($superAdmin)->post('/administration/governance/WORKFLOW', $this->workflowPayload('lpj-work-queue-v2', 7))
            ->assertSessionHasNoErrors();
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $task = SmartTask::query()->where('metadata->lpj_id', $workflow['lpj']->id)->firstOrFail();
        $originalDueAt = $task->due_at;
        $this->assertSame('lpj-work-queue-v2', $task->metadata['workflow_policy_version']);

        $this->actingAs($superAdmin)->post('/administration/governance/WORKFLOW', $this->workflowPayload('lpj-work-queue-v3', 1))
            ->assertSessionHasNoErrors();
        app(SynchronizeLpjWorkflowTasksAction::class)->handle($workflow['lpj']->fresh(), $superAdmin);

        $task->refresh();
        $this->assertTrue($originalDueAt->equalTo($task->due_at));
        $this->assertSame('lpj-work-queue-v2', $task->metadata['workflow_policy_version']);
    }

    public function test_retention_and_ai_controls_publish_safe_operational_settings(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'server-only-secret');
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);

        $this->actingAs($superAdmin)->post('/administration/governance/RETENTION', [
            'version' => 'lpj-archive-retention-v2',
            'change_summary' => 'Masa simpan dan skema paket arsip baru ditetapkan.',
            'retention_years' => 15,
            'schema_version' => 2,
            'disk' => 'local',
        ])->assertSessionHasNoErrors();
        $this->actingAs($superAdmin)->post('/administration/governance/AI', [
            'version' => 'lpj-ai-governance-v2',
            'change_summary' => 'Provider eksternal dinonaktifkan sampai tinjauan keamanan selesai.',
            'external_provider_enabled' => false,
            'daily_token_budget' => 5000,
            'monthly_token_budget' => 100000,
            'warning_percent' => 75,
            'user_rate_per_minute' => 8,
            'unit_rate_per_minute' => 25,
            'organization_rate_per_minute' => 100,
            'review_disclaimer' => 'Rekomendasi wajib diverifikasi manusia.',
        ])->assertSessionHasNoErrors();

        $registry = app(GovernancePolicyRegistry::class);
        $this->assertSame(15, $registry->get(GovernancePolicyType::Retention)['retention_years']);
        $this->assertSame(2, $registry->get(GovernancePolicyType::Retention)['schema_version']);

        $workflow = $this->completeWorkflow($unit);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'PRECHECK');
        $this->assertSame('local-rules-engine', $review->model);
        $this->assertFalse($registry->get(GovernancePolicyType::Ai)['external_provider_enabled']);
        $this->assertSame(100000, $registry->get(GovernancePolicyType::Ai)['monthly_token_budget']);
        $this->assertSame(8, $registry->get(GovernancePolicyType::Ai)['user_rate_per_minute']);
    }

    /** @return array<string, mixed> */
    private function verificationPayload(string $version, string $summary, string $administration = 'Mandat dan identitas kegiatan sesuai'): array
    {
        return [
            'version' => $version,
            'change_summary' => $summary,
            'clarification_due_days' => 4,
            'administration_checklist' => $administration."\nPeserta dan dokumen lengkap",
            'finance_checklist' => "RAB dan realisasi konsisten\nRekonsiliasi telah diperiksa",
            'substantive_checklist' => "Output sesuai rencana\nOutcome didukung bukti",
        ];
    }

    /** @return array<string, mixed> */
    private function systemPayload(): array
    {
        return [
            'version' => 'lpj-system-governance-v2',
            'change_summary' => 'Klasifikasi dan pesan pemeliharaan diperbarui.',
            'data_classification' => 'Internal terbatas',
            'maintenance_message' => 'Pemeliharaan terjadwal Sabtu pukul 22.00 WIB.',
            'support_message' => 'Hubungi administrator nasional.',
        ];
    }

    /** @return array<string, mixed> */
    private function workflowPayload(string $version, int $verificationDays): array
    {
        return [
            'version' => $version,
            'change_summary' => 'SLA workflow diperbarui untuk tugas yang diterbitkan berikutnya.',
            'verification_due_days' => $verificationDays,
            'approval_due_days' => 2,
            'pb_verification_due_days' => 2,
            'archive_due_days' => 2,
            'revision_due_days' => 3,
            'escalation_lead_hours' => 12,
        ];
    }
}
