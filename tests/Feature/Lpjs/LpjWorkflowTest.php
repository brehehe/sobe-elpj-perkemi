<?php

namespace Tests\Feature\Lpjs;

use App\Actions\ApproveLpjAction;
use App\Actions\ArchiveLpjAction;
use App\Actions\OpenFindingAction;
use App\Actions\ReviewCorrectiveActionAction;
use App\Actions\SubmitCorrectiveActionAction;
use App\Actions\SubmitLpjAction;
use App\Actions\VerifyLpjAction;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LpjStatus;
use App\Enums\TaskStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\AuditEvent;
use App\Models\Disposition;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\SmartTask;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LpjWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_complete_workflow_reaches_immutable_archive(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $unit = $workflow['unit'];
        $lpj = $workflow['lpj'];
        $adminVerifier = $this->userWithRole('Verifier Admin', $unit);
        $financeVerifier = $this->userWithRole('Verifier Finance', $unit);
        $substantiveVerifier = $this->userWithRole('Verifier Substantive', $unit);
        $approver = $this->userWithRole('Approver', $unit);
        $pbVerifier = $this->userWithRole('PB Verifier', Unit::factory()->create());

        $version = app(SubmitLpjAction::class)->handle($lpj, $workflow['owner'], ['notes' => 'Pengajuan final']);
        $this->assertTrue($version->is_immutable);
        $this->assertSame(hash('sha256', json_encode($version->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), $version->checksum);
        $systemVerification = $version->verifications->first(fn ($verification) => $verification->stage === VerificationStage::System);
        $this->assertNotNull($systemVerification);
        $this->assertSame(VerificationStatus::Verified, $systemVerification->status);
        $this->assertCount(22, $systemVerification->checklist);

        foreach ([
            [VerificationStage::Administration, $adminVerifier],
            [VerificationStage::Finance, $financeVerifier],
            [VerificationStage::Substantive, $substantiveVerifier],
        ] as [$stage, $verifier]) {
            app(VerifyLpjAction::class)->handle($lpj->fresh(), $verifier, $this->verificationPayload($lpj, $stage));
        }

        $this->assertSame(LpjStatus::ReadyForApproval, $lpj->fresh()->status);
        app(ApproveLpjAction::class)->handle($lpj->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        app(ApproveLpjAction::class)->handle($lpj->fresh(), $pbVerifier, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $archive = app(ArchiveLpjAction::class)->handle($lpj->fresh(), $workflow['owner']);

        $this->assertSame(LpjStatus::Archived, $lpj->fresh()->status);
        Storage::disk('local')->assertExists($archive->path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($archive->path)), $archive->checksum);
        $this->assertGreaterThanOrEqual(7, AuditEvent::query()->where('auditable_id', $lpj->id)->count());
    }

    public function test_return_and_resubmit_create_a_new_version_without_mutating_the_first_snapshot(): void
    {
        $workflow = $this->completeWorkflow();
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $first = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $firstSnapshot = $first->snapshot;
        $firstChecksum = $first->checksum;

        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $verifier, [
            'stage' => VerificationStage::Administration->value,
            'status' => VerificationStatus::Returned->value,
            'notes' => 'Narasi perlu diperbaiki.',
            'checklist' => $this->verificationChecklist($first, VerificationStage::Administration, 0),
            'evidence_ids' => [$workflow['activity']->evidence()->value('id')],
        ]);
        $this->assertSame(LpjStatus::Returned, $workflow['lpj']->fresh()->status);

        $workflow['activity']->evaluations()->firstOrFail()->update(['summary' => 'Narasi hasil telah diperbaiki.']);
        $second = app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);

        $this->assertSame(2, $second->version);
        $this->assertSame($firstSnapshot, $first->fresh()->snapshot);
        $this->assertSame($firstChecksum, $first->fresh()->checksum);
        $this->assertNotSame($firstChecksum, $second->checksum);
        $this->assertSame(2, $workflow['lpj']->versions()->count());
    }

    public function test_submit_is_blocked_when_required_sources_are_missing(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->evaluations()->delete();

        try {
            app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evaluation', $exception->errors());
        }

        $this->assertSame(0, $workflow['lpj']->versions()->count());
    }

    public function test_submitter_cannot_verify_their_own_lpj(): void
    {
        $workflow = $this->completeWorkflow();
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $this->expectException(ValidationException::class);
        app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner'], [
            'stage' => VerificationStage::Administration->value,
            'status' => VerificationStatus::Verified->value,
        ]);
    }

    public function test_verification_endpoint_stores_checklist_evidence_and_material_exception(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $verifier = $this->userWithRole('Verifier Finance', $workflow['unit']);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $evidenceId = $workflow['activity']->evidence()->value('id');
        app(VerifyLpjAction::class)->handle(
            $workflow['lpj']->fresh(),
            $adminVerifier,
            $this->verificationPayload($workflow['lpj'], VerificationStage::Administration),
        );

        $response = $this->actingAs($verifier)->post("/lpjs/{$workflow['lpj']->id}/verifications", [
            'stage' => VerificationStage::Finance->value,
            'status' => VerificationStatus::Verified->value,
            'notes' => 'Rekonsiliasi dan bukti sesuai.',
            'checklist' => [
                ['item' => 'RAB dan realisasi konsisten', 'result' => 'PASS', 'notes' => null],
                ['item' => 'Transaksi serta rekonsiliasi telah diperiksa', 'result' => 'PASS', 'notes' => 'Referensi bank sesuai.'],
                ['item' => 'Bukti pembayaran relevan dan terbaca', 'result' => 'PASS', 'notes' => null],
            ],
            'evidence_ids' => [$evidenceId],
            'material_exception' => 'Tidak ada exception material.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $verification = $version->verifications()->where('stage', VerificationStage::Finance)->firstOrFail();
        $this->assertSame([['item' => 'RAB dan realisasi konsisten', 'result' => 'PASS', 'notes' => null], ['item' => 'Transaksi serta rekonsiliasi telah diperiksa', 'result' => 'PASS', 'notes' => 'Referensi bank sesuai.'], ['item' => 'Bukti pembayaran relevan dan terbaca', 'result' => 'PASS', 'notes' => null]], $verification->checklist);
        $this->assertSame([$evidenceId], $verification->evidence_ids);
        $this->assertSame('Tidak ada exception material.', $verification->material_exception);
    }

    public function test_system_verification_cannot_be_changed_manually(): void
    {
        $workflow = $this->completeWorkflow();
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $this->actingAs($verifier)->post("/lpjs/{$workflow['lpj']->id}/verifications", [
            'stage' => VerificationStage::System->value,
            'status' => VerificationStatus::Returned->value,
            'notes' => 'Percobaan perubahan manual.',
            'checklist' => [['item' => 'System check', 'result' => 'FAIL', 'notes' => null]],
            'evidence_ids' => [$workflow['activity']->evidence()->value('id')],
        ])->assertSessionHasErrors('stage');

        $systemVerification = $workflow['lpj']->fresh()->currentVersionRecord->verifications()->where('stage', VerificationStage::System)->firstOrFail();
        $this->assertSame(VerificationStatus::Verified, $systemVerification->status);
    }

    public function test_returns_stage_error_when_verifier_skips_a_mandatory_gate(): void
    {
        $workflow = $this->completeWorkflow();
        $financeVerifier = $this->userWithRole('Verifier Finance', $workflow['unit']);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $response = $this->actingAs($financeVerifier)->post("/lpjs/{$workflow['lpj']->id}/verifications", [
            ...$this->verificationPayload($workflow['lpj'], VerificationStage::Finance),
        ]);

        $response->assertSessionHasErrors([
            'stage' => 'Urutan verifikasi wajib dipenuhi. Tahap berikutnya: Administrasi.',
        ]);
        $financeVerification = $version->verifications()->where('stage', VerificationStage::Finance)->firstOrFail();
        $this->assertSame(VerificationStatus::Pending, $financeVerification->status);
        $this->assertSame(LpjStatus::InVerification, $workflow['lpj']->fresh()->status);
    }

    public function test_returns_checklist_error_when_verified_decision_contains_a_failed_item(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $payload = $this->verificationPayload($workflow['lpj'], VerificationStage::Administration);
        $payload['checklist'] = $this->verificationChecklist($version, VerificationStage::Administration, 0);

        $response = $this->actingAs($adminVerifier)->post("/lpjs/{$workflow['lpj']->id}/verifications", $payload);

        $response->assertSessionHasErrors([
            'checklist' => 'Keputusan VERIFIED tidak dapat disimpan selama checklist masih memiliki hasil gagal.',
        ]);
        $administrationVerification = $version->verifications()->where('stage', VerificationStage::Administration)->firstOrFail();
        $this->assertSame(VerificationStatus::Pending, $administrationVerification->status);
    }

    public function test_clarification_creates_a_task_and_verification_resumes_only_after_review(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $evidenceId = $workflow['activity']->evidence()->value('id');
        $clarificationPayload = $this->verificationPayload($workflow['lpj'], VerificationStage::Administration);
        $clarificationPayload['status'] = VerificationStatus::ClarificationRequired->value;
        $clarificationPayload['notes'] = 'Mohon jelaskan perbedaan identitas pada surat tugas.';

        $this->actingAs($adminVerifier)
            ->post("/lpjs/{$workflow['lpj']->id}/verifications", $clarificationPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $disposition = Disposition::query()->firstOrFail();
        $this->assertSame('LPJ_VERIFICATION_CLARIFICATION:ADMINISTRATION', $disposition->context);
        $this->assertSame($workflow['owner']->id, $disposition->to_user_id);
        $this->assertSame(LpjStatus::ClarificationRequired, $workflow['lpj']->fresh()->status);
        $this->assertSame(VerificationStatus::ClarificationRequired, $version->verifications()->where('stage', VerificationStage::Administration)->firstOrFail()->status);
        $this->assertSame(TaskStatus::Open, SmartTask::query()->where('metadata->disposition_id', $disposition->id)->value('status'));

        $this->actingAs($adminVerifier)
            ->post("/lpjs/{$workflow['lpj']->id}/verifications", $this->verificationPayload($workflow['lpj'], VerificationStage::Administration))
            ->assertSessionHasErrors([
                'stage' => 'Klarifikasi tahap ini harus ditutup oleh pemeriksa sebelum verifikasi dilanjutkan.',
            ]);

        foreach (['ACKNOWLEDGED', 'IN_PROGRESS'] as $status) {
            $this->actingAs($workflow['owner'])
                ->patch("/dispositions/{$disposition->id}", ['status' => $status])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }
        $this->actingAs($workflow['owner'])->patch("/dispositions/{$disposition->id}", [
            'status' => 'EVIDENCE_SUBMITTED',
            'evidence_ids' => [$evidenceId],
            'notes' => 'Identitas dan surat tugas telah diklarifikasi.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($adminVerifier)->patch("/dispositions/{$disposition->id}", [
            'status' => 'CLOSED',
            'notes' => 'Klarifikasi diterima dan bukti sesuai.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(LpjStatus::InVerification, $workflow['lpj']->fresh()->status);
        $this->actingAs($adminVerifier)
            ->post("/lpjs/{$workflow['lpj']->id}/verifications", $this->verificationPayload($workflow['lpj'], VerificationStage::Administration))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(VerificationStatus::Verified, $version->verifications()->where('stage', VerificationStage::Administration)->firstOrFail()->status);
    }

    public function test_finding_decision_requires_an_open_finding_and_records_the_domain_state(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $payload = $this->verificationPayload($workflow['lpj'], VerificationStage::Administration);
        $payload['status'] = VerificationStatus::Finding->value;
        $payload['notes'] = 'Dokumen mandat tidak konsisten.';
        $payload['checklist'] = $this->verificationChecklist($version, VerificationStage::Administration, 0);

        $this->actingAs($adminVerifier)
            ->post("/lpjs/{$workflow['lpj']->id}/verifications", $payload)
            ->assertSessionHasErrors([
                'status' => 'Buat temuan dan tetapkan CAPA sebelum mencatat keputusan FINDING.',
            ]);

        app(OpenFindingAction::class)->handle($workflow['lpj']->fresh(), $adminVerifier, [
            'severity' => 'HIGH',
            'title' => 'Identitas mandat tidak konsisten',
            'criteria' => 'Identitas pelaksana harus konsisten dengan mandat.',
            'condition' => 'Nama pelaksana berbeda pada laporan.',
            'cause' => 'Dokumen lama digunakan saat penyusunan.',
            'risk' => 'Akuntabilitas pelaksana tidak dapat dipastikan.',
            'recommendation' => 'Perbarui dokumen dan ajukan bukti CAPA.',
            'detail' => 'Perbedaan terdapat pada nama penanggung jawab.',
            'owner_id' => $workflow['owner']->id,
        ]);

        $this->actingAs($adminVerifier)
            ->post("/lpjs/{$workflow['lpj']->id}/verifications", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(VerificationStatus::Finding, $version->verifications()->where('stage', VerificationStage::Administration)->firstOrFail()->status);
        $this->assertSame(LpjStatus::FindingOpen, $workflow['lpj']->fresh()->status);
    }

    public function test_verification_center_exposes_the_versioned_gate_and_actor_availability(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $response = $this->actingAs($adminVerifier)->get("/lpjs/{$workflow['lpj']->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->component('lpjs/show')
            ->where('verificationWorkflow.policy_version', 'lpj-verification-v1')
            ->where('verificationWorkflow.completed', 1)
            ->where('verificationWorkflow.total', 4)
            ->where('verificationWorkflow.next_stage', 'ADMINISTRATION')
            ->where('verificationWorkflow.can_verify_next_stage', true)
            ->has('verificationWorkflow.stages', 4));
    }

    public function test_verification_api_returns_the_next_gate_in_versioned_metadata(): void
    {
        $workflow = $this->completeWorkflow();
        $adminVerifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);

        $response = $this->actingAs($adminVerifier)->postJson(
            "/api/v1/lpjs/{$workflow['lpj']->id}/verifications",
            $this->verificationPayload($workflow['lpj'], VerificationStage::Administration),
        );

        $response->assertOk()
            ->assertJsonPath('data.status', LpjStatus::InVerification->value)
            ->assertJsonPath('meta.verification_workflow.policy_version', 'lpj-verification-v1')
            ->assertJsonPath('meta.verification_workflow.next_stage', VerificationStage::Finance->value)
            ->assertJsonPath('meta.verification_workflow.completed', 2)
            ->assertJsonCount(4, 'meta.verification_workflow.stages');
    }

    public function test_finding_requires_capa_review_by_a_different_user_before_resolution(): void
    {
        $workflow = $this->completeWorkflow();
        $finder = $this->userWithRole('Verifier Finance', $workflow['unit']);
        $reviewer = $this->userWithRole('Verifier Admin', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $finding = app(OpenFindingAction::class)->handle($workflow['lpj']->fresh(), $finder, [
            'severity' => 'HIGH',
            'title' => 'Bukti pembayaran perlu dilengkapi',
            'detail' => 'Lampirkan dokumen bank yang sesuai referensi transaksi.',
            'due_at' => now()->addWeek(),
        ]);
        $correctiveAction = app(SubmitCorrectiveActionAction::class)->handle($finding, $workflow['owner'], 'Dokumen bank sudah ditambahkan.');

        $this->assertSame('CAPA_SUBMITTED', $finding->fresh()->status->value);
        app(ReviewCorrectiveActionAction::class)->handle($finding, $correctiveAction, $reviewer, 'ACCEPTED', 'Bukti dinilai cukup.');

        $this->assertSame('RESOLVED', $finding->fresh()->status->value);
        $this->assertSame('ACCEPTED', $correctiveAction->fresh()->status);
        $this->assertSame(LpjStatus::InVerification, $workflow['lpj']->fresh()->status);
    }

    public function test_capa_submitter_cannot_review_their_own_action(): void
    {
        $workflow = $this->completeWorkflow();
        $finder = $this->userWithRole('Verifier Finance', $workflow['unit']);
        app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $finding = app(OpenFindingAction::class)->handle($workflow['lpj']->fresh(), $finder, [
            'severity' => 'MEDIUM', 'title' => 'Klarifikasi', 'detail' => 'Perlu klarifikasi.',
        ]);
        $action = app(SubmitCorrectiveActionAction::class)->handle($finding, $workflow['owner'], 'Klarifikasi disampaikan.');

        $this->expectException(ValidationException::class);
        app(ReviewCorrectiveActionAction::class)->handle($finding, $action, $workflow['owner'], 'ACCEPTED', null);
    }

    /** @return array<string, mixed> */
    private function verificationPayload(Lpj $lpj, VerificationStage $stage): array
    {
        $version = $lpj->currentVersionRecord()->firstOrFail();

        return [
            'stage' => $stage->value,
            'status' => VerificationStatus::Verified->value,
            'notes' => 'Checklist lengkap.',
            'checklist' => $this->verificationChecklist($version, $stage),
            'evidence_ids' => [$lpj->activity->evidence()->value('id')],
        ];
    }

    /** @return list<array{item: string, result: string, notes: string|null}> */
    private function verificationChecklist(LpjVersion $version, VerificationStage $stage, ?int $failedIndex = null): array
    {
        return collect(data_get($version->snapshot, "verification.stages.{$stage->value}.checklist", []))
            ->values()
            ->map(fn (string $item, int $index): array => [
                'item' => $item,
                'result' => $index === $failedIndex ? 'FAIL' : 'PASS',
                'notes' => $index === $failedIndex ? 'Perlu perbaikan' : null,
            ])->all();
    }
}
