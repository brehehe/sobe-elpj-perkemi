<?php

namespace Tests\Feature\Lpjs;

use App\Actions\ApproveLpjAction;
use App\Actions\SubmitLpjAction;
use App\Actions\UpdateDispositionStatusAction;
use App\Actions\VerifyLpjAction;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\DispositionStatus;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\ApprovalDelegation;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\Unit;
use App\Models\User;
use App\Services\LpjApprovalGate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LpjApprovalDecisionPackTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_approval_freezes_complete_decision_pack_and_authority_metadata(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);

        $approval = app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
            'notes' => 'Decision pack telah diperiksa.',
        ]);

        $this->assertSame('lpj-approval-authority-v1', $approval->authority_policy_version);
        $this->assertSame('DIRECT:APPROVER', $approval->authority_reference);
        $this->assertSame('DIRECT', $approval->authority_snapshot['source']);
        $this->assertSame($workflow['version']->checksum, $approval->lpj_checksum);
        $this->assertSame(
            hash('sha256', json_encode($approval->decision_pack, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $approval->decision_pack_checksum,
        );
        $this->assertSame(
            ['schema_version', 'lpj', 'activity', 'financial', 'verifications', 'findings', 'approved_exceptions', 'open_dispositions', 'audit_history', 'source_references'],
            array_keys($approval->decision_pack),
        );
        $this->assertCount(4, $approval->decision_pack['verifications']);
        $this->assertSame(1, $approval->decision_pack['financial']['transaction_count']);
        $this->assertSame(1, $approval->decision_pack['financial']['reconciled_count']);
        $this->assertNotEmpty($approval->signature_hash);
        $this->assertSame(LpjStatus::Approved, $workflow['lpj']->fresh()->status);
    }

    public function test_active_delegation_grants_authority_with_unit_and_amount_scope(): void
    {
        $workflow = $this->readyForApproval();
        $delegate = $this->userWithRole('Auditor', $workflow['unit']);
        $grantor = $this->userWithRole('Super Admin', Unit::factory()->create());
        ApprovalDelegation::factory()->create([
            'delegate_id' => $delegate->id,
            'granted_by' => $grantor->id,
            'unit_id' => $workflow['unit']->id,
            'level' => ApprovalLevel::Approver,
            'max_amount' => 2_000_000,
            'reference' => 'SK-DELEGASI-2026-001',
        ]);

        $response = $this->actingAs($delegate)->post("/lpjs/{$workflow['lpj']->id}/approvals", [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $approval = $workflow['version']->approvals()->firstOrFail();
        $this->assertSame('DELEGATION', $approval->authority_snapshot['source']);
        $this->assertSame('SK-DELEGASI-2026-001', $approval->authority_reference);
    }

    public function test_expired_or_insufficient_delegation_cannot_authorize_decision(): void
    {
        $workflow = $this->readyForApproval();
        $delegate = $this->userWithRole('Auditor', $workflow['unit']);
        $grantor = $this->userWithRole('Super Admin', Unit::factory()->create());
        ApprovalDelegation::factory()->create([
            'delegate_id' => $delegate->id,
            'granted_by' => $grantor->id,
            'unit_id' => $workflow['unit']->id,
            'max_amount' => 100_000,
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->subDay(),
        ]);

        $this->actingAs($delegate)->post("/lpjs/{$workflow['lpj']->id}/approvals", [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ])->assertForbidden();

        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_direct_authority_checks_active_user_unit_and_budget_value(): void
    {
        $workflow = $this->readyForApproval();
        config(['lpj.approval_policy.levels.APPROVER.max_amount' => 1_000_000]);
        $overLimitApprover = $this->userWithRole('Approver', $workflow['unit']);
        $inactiveApprover = $this->userWithRole('Approver', $workflow['unit']);
        $inactiveApprover->update(['is_active' => false]);

        foreach ([
            $overLimitApprover,
            $this->userWithRole('Approver', Unit::factory()->create()),
            $inactiveApprover,
        ] as $user) {
            try {
                app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $user->fresh(), [
                    'level' => ApprovalLevel::Approver->value,
                    'decision' => ApprovalDecision::Approved->value,
                ]);
                $this->fail('Invalid authority should block approval.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('approver', $exception->errors());
            }
        }

        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_conflict_of_interest_blocks_submitter_and_domain_verifier(): void
    {
        $workflow = $this->readyForApproval();
        $workflow['owner']->assignRole('Approver');

        try {
            app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']->fresh(), [
                'level' => ApprovalLevel::Approver->value,
                'decision' => ApprovalDecision::Approved->value,
            ]);
            $this->fail('Submitter conflict should block approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approver', $exception->errors());
        }

        $verifier = $workflow['verifiers'][0];
        $verifier->assignRole('Approver');
        $this->expectException(ValidationException::class);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $verifier->fresh(), [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
    }

    public function test_clarification_creates_task_and_resumes_same_approval_level_after_review(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::ClarificationRequired->value,
            'notes' => 'Jelaskan perbedaan jadwal pelaksanaan.',
        ]);

        $disposition = $workflow['version']->dispositions()->firstOrFail();
        $this->assertSame('LPJ_APPROVAL_CLARIFICATION:APPROVER', $disposition->context);
        $this->assertSame($workflow['owner']->id, $disposition->to_user_id);
        $this->assertSame(LpjStatus::ClarificationRequired, $workflow['lpj']->fresh()->status);
        $this->assertDatabaseHas('smart_tasks', ['assignee_id' => $workflow['owner']->id, 'status' => 'OPEN']);

        $updates = app(UpdateDispositionStatusAction::class);
        $updates->handle($disposition->fresh(), $workflow['owner'], ['status' => DispositionStatus::Acknowledged->value]);
        $updates->handle($disposition->fresh(), $workflow['owner'], ['status' => DispositionStatus::InProgress->value]);
        $updates->handle($disposition->fresh(), $workflow['owner'], [
            'status' => DispositionStatus::EvidenceSubmitted->value,
            'notes' => 'Kronologi jadwal telah dijelaskan pada bukti.',
            'evidence_ids' => [$workflow['lpj']->activity->evidence()->value('id')],
        ]);
        $updates->handle($disposition->fresh(), $approver, [
            'status' => DispositionStatus::Closed->value,
            'notes' => 'Klarifikasi memadai.',
        ]);

        $this->assertSame(LpjStatus::ReadyForApproval, $workflow['lpj']->fresh()->status);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $this->assertSame(2, $workflow['version']->approvals()->where('level', ApprovalLevel::Approver->value)->count());
        $this->assertSame(LpjStatus::Approved, $workflow['lpj']->fresh()->status);
    }

    public function test_pb_revalidates_that_prior_approval_matches_active_snapshot(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $pb = $this->userWithRole('PB Verifier', Unit::factory()->create());
        $approval = app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        DB::table('approvals')->where('id', $approval->id)->update(['lpj_checksum' => str_repeat('0', 64)]);

        try {
            app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pb, [
                'level' => ApprovalLevel::Pb->value,
                'decision' => ApprovalDecision::Approved->value,
            ]);
            $this->fail('PB gate should reject a stale prior approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }

        $this->assertSame(LpjStatus::Approved, $workflow['lpj']->fresh()->status);
    }

    public function test_pb_clarification_resumes_at_approved_without_replacing_prior_decision(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $pb = $this->userWithRole('PB Verifier', Unit::factory()->create());
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pb, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::ClarificationRequired->value,
            'notes' => 'Konfirmasi referensi penutupan kegiatan.',
        ]);

        $disposition = $workflow['version']->dispositions()->firstOrFail();
        $updates = app(UpdateDispositionStatusAction::class);
        $updates->handle($disposition->fresh(), $workflow['owner'], ['status' => DispositionStatus::Acknowledged->value]);
        $updates->handle($disposition->fresh(), $workflow['owner'], ['status' => DispositionStatus::InProgress->value]);
        $updates->handle($disposition->fresh(), $workflow['owner'], [
            'status' => DispositionStatus::EvidenceSubmitted->value,
            'notes' => 'Referensi final telah dikonfirmasi.',
            'evidence_ids' => [$workflow['lpj']->activity->evidence()->value('id')],
        ]);
        $updates->handle($disposition->fresh(), $pb, [
            'status' => DispositionStatus::Closed->value,
            'notes' => 'Konfirmasi diterima.',
        ]);

        $this->assertSame(LpjStatus::Approved, $workflow['lpj']->fresh()->status);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pb, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $this->assertSame(2, $workflow['version']->approvals()->where('level', ApprovalLevel::Pb->value)->count());
        $this->assertSame(LpjStatus::PbVerified, $workflow['lpj']->fresh()->status);
    }

    public function test_api_returns_updated_approval_workflow_contract(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);

        $response = $this->actingAs($approver)->postJson("/api/v1/lpjs/{$workflow['lpj']->id}/approvals", [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.authority_policy_version', 'lpj-approval-authority-v1')
            ->assertJsonPath('data.authority_reference', 'DIRECT:APPROVER')
            ->assertJsonPath('meta.approval_workflow.level', 'PB')
            ->assertJsonStructure(['data' => ['decision_pack', 'decision_pack_checksum', 'signature_hash'], 'meta' => ['approval_workflow' => ['checks', 'decision_pack']]]);
    }

    public function test_replayed_terminal_decision_is_rejected_without_duplicate_record(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        $payload = [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ];

        $this->actingAs($approver)->post("/lpjs/{$workflow['lpj']->id}/approvals", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->actingAs($approver)->post("/lpjs/{$workflow['lpj']->id}/approvals", $payload)
            ->assertSessionHasErrors('status');

        $this->assertSame(1, $workflow['version']->approvals()->where('level', ApprovalLevel::Approver->value)->count());
    }

    public function test_pb_verifier_can_open_approved_lpj_from_another_unit(): void
    {
        $workflow = $this->readyForApproval();
        $approver = $this->userWithRole('Approver', $workflow['unit']);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);
        $pb = $this->userWithRole('PB Verifier', Unit::factory()->create());
        $this->assertEmpty(app(LpjApprovalGate::class)->evaluate($workflow['lpj']->fresh(), $pb)['blockers']);

        $this->actingAs($pb)->get("/lpjs/{$workflow['lpj']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('lpjs/show')
                ->where('approvalWorkflow.level', 'PB')
                ->where('approvalWorkflow.can_approve', true));
    }

    /** @return array{unit: Unit, owner: User, lpj: Lpj, version: LpjVersion, verifiers: list<User>} */
    private function readyForApproval(): array
    {
        $workflow = $this->completeWorkflow();
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $verifiers = [
            $this->userWithRole('Verifier Admin', $workflow['unit']),
            $this->userWithRole('Verifier Finance', $workflow['unit']),
            $this->userWithRole('Verifier Substantive', $workflow['unit']),
        ];
        $stages = [VerificationStage::Administration, VerificationStage::Finance, VerificationStage::Substantive];

        foreach ($stages as $index => $stage) {
            app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $verifiers[$index], [
                'stage' => $stage->value,
                'status' => VerificationStatus::Verified->value,
                'notes' => 'Checklist lengkap.',
                'checklist' => $this->verificationChecklist($version, $stage),
                'evidence_ids' => [$workflow['activity']->evidence()->value('id')],
            ]);
        }

        return [...$workflow, 'version' => $version, 'verifiers' => $verifiers];
    }

    /** @return list<array{item: string, result: string, notes: null}> */
    private function verificationChecklist(LpjVersion $version, VerificationStage $stage): array
    {
        return collect(data_get($version->snapshot, "verification.stages.{$stage->value}.checklist", []))
            ->map(fn (string $item): array => ['item' => $item, 'result' => 'PASS', 'notes' => null])
            ->values()
            ->all();
    }
}
