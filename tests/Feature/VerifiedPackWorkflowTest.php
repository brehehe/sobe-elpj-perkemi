<?php

namespace Tests\Feature;

use App\Actions\ApproveLpjAction;
use App\Actions\ArchiveLpjAction;
use App\Actions\SubmitLpjAction;
use App\Actions\VerifyLpjAction;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Archive;
use App\Models\AuditEvent;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifiedPack;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class VerifiedPackWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPermissions();
    }

    public function test_pb_approval_issues_hashed_verified_pack_before_verified_status(): void
    {
        $workflow = $this->readyForPb();
        $pb = $this->userWithRole('PB Verifier', Unit::factory()->create());

        $approval = app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pb, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);

        $verifiedPack = $workflow['version']->verifiedPack()->firstOrFail();
        $content = Storage::disk('local')->get($verifiedPack->path);
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(LpjStatus::PbVerified, $workflow['lpj']->fresh()->status);
        $this->assertSame($approval->id, $verifiedPack->approval_id);
        $this->assertSame($workflow['version']->checksum, $verifiedPack->lpj_checksum);
        $this->assertSame($approval->signature_hash, $verifiedPack->approval_signature_hash);
        $this->assertSame(hash('sha256', $content), $verifiedPack->checksum);
        $this->assertSame($verifiedPack->id, $payload['verification_id']);
        $this->assertSame($approval->id, $payload['lineage']['approval_id']);
        $this->assertSame($workflow['version']->id, $payload['lineage']['lpj_version_id']);
        $this->assertArrayHasKey('evidence_manifest', $payload);
        $this->assertArrayHasKey('audit_summary', $payload);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $verifiedPack->id,
            'event_type' => 'lpj.verified_pack_issued',
            'result' => 'SUCCEEDED',
        ]);
    }

    public function test_public_reference_validates_integrity_without_authentication(): void
    {
        $verifiedPack = $this->issueVerifiedPack();

        $this->get("/verify/{$verifiedPack->public_token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/verification')
                ->where('verification.status', 'VALID')
                ->where('verification.verification_number', $verifiedPack->verification_number)
                ->where('verification.approval_id', $verifiedPack->approval_id)
                ->where('verification.checks.pack_checksum_valid', true)
                ->missing('verification.public_token')
                ->missing('verification.path'));

        $this->getJson("/api/v1/verifications/{$verifiedPack->public_token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'VALID')
            ->assertJsonPath('data.verification_number', $verifiedPack->verification_number)
            ->assertJsonMissingPath('data.public_token')
            ->assertJsonMissingPath('data.path');

        $this->assertSame(2, AuditEvent::query()
            ->where('auditable_id', $verifiedPack->id)
            ->where('event_type', 'verified_pack.public_checked')
            ->where('result', 'SUCCEEDED')
            ->count());
        $this->get('/verify/not-a-valid-token')->assertNotFound();
    }

    public function test_tampered_verified_pack_is_reported_and_blocks_download_and_archive(): void
    {
        $verifiedPack = $this->issueVerifiedPack();
        Storage::disk($verifiedPack->disk)->put($verifiedPack->path, '{"tampered":true}');

        $this->get("/verify/{$verifiedPack->public_token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('verification.status', 'INVALID')
                ->where('verification.checks.pack_checksum_valid', false)
                ->where('verification.checks.lineage_valid', false));

        $this->actingAs($verifiedPack->issuer)->get("/verified-packs/{$verifiedPack->id}/download")
            ->assertConflict();

        try {
            app(ArchiveLpjAction::class)->handle($verifiedPack->lpjVersion->lpj, $verifiedPack->issuer);
            $this->fail('Tampered Verified Pack should block archival.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verified_pack', $exception->errors());
        }

        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $verifiedPack->id,
            'event_type' => 'verified_pack.downloaded',
            'result' => 'FAILED',
        ]);
    }

    public function test_archive_uses_versioned_retention_and_revalidates_both_hashes_on_download(): void
    {
        config(['lpj.archive_policy.retention_years' => 7]);
        $verifiedPack = $this->issueVerifiedPack();
        $archive = app(ArchiveLpjAction::class)->handle($verifiedPack->lpjVersion->lpj, $verifiedPack->issuer);
        $archivePayload = json_decode(Storage::disk($archive->disk)->get($archive->path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($verifiedPack->id, $archive->verified_pack_id);
        $this->assertSame($verifiedPack->checksum, $archive->verified_pack_checksum);
        $this->assertSame('lpj-archive-retention-v1', $archive->retention_policy_version);
        $this->assertSame(7, (int) $archive->archived_at->diffInYears($archive->retention_until));
        $this->assertSame($verifiedPack->id, $archivePayload['verified_pack']['id']);
        $this->assertSame('VALID', $archivePayload['verified_pack']['integrity']['status']);

        $this->actingAs($verifiedPack->issuer)->get("/archives/{$archive->id}/download")
            ->assertOk();

        Storage::disk($archive->disk)->put($archive->path, '{"tampered":true}');
        $this->actingAs($verifiedPack->issuer)->get("/archives/{$archive->id}/download")
            ->assertConflict();
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $archive->id,
            'event_type' => 'archive.accessed',
            'result' => 'FAILED',
        ]);
    }

    public function test_verified_pack_and_archive_records_are_immutable(): void
    {
        $verifiedPack = $this->issueVerifiedPack();

        try {
            $verifiedPack->update(['checksum' => str_repeat('0', 64)]);
            $this->fail('Verified Pack must be immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Verified packs are immutable.', $exception->getMessage());
        }

        $archive = app(ArchiveLpjAction::class)->handle($verifiedPack->lpjVersion->lpj, $verifiedPack->issuer);

        $this->expectException(LogicException::class);
        $archive->delete();
    }

    public function test_archive_is_blocked_when_verified_pack_is_missing(): void
    {
        $workflow = $this->readyForPb();
        DB::table('lpjs')->where('id', $workflow['lpj']->id)->update([
            'status' => LpjStatus::PbVerified->value,
            'pb_verified_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(ArchiveLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);
    }

    public function test_verified_pack_and_archive_factories_preserve_lineage(): void
    {
        $verifiedPack = VerifiedPack::factory()->create();
        $archive = Archive::factory()->create();

        $this->assertSame($verifiedPack->approval->lpj_version_id, $verifiedPack->lpj_version_id);
        $this->assertSame($verifiedPack->approval->signature_hash, $verifiedPack->approval_signature_hash);
        $this->assertSame($archive->verifiedPack->lpj_version_id, $archive->lpj_version_id);
        $this->assertSame($archive->verifiedPack->checksum, $archive->verified_pack_checksum);
    }

    private function issueVerifiedPack(): VerifiedPack
    {
        $workflow = $this->readyForPb();
        $pb = $this->userWithRole('PB Verifier', Unit::factory()->create());
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $pb, [
            'level' => ApprovalLevel::Pb->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);

        return $workflow['version']->verifiedPack()->with(['issuer', 'lpjVersion.lpj'])->firstOrFail();
    }

    /** @return array{unit: Unit, owner: User, lpj: Lpj, version: LpjVersion} */
    private function readyForPb(): array
    {
        $workflow = $this->completeWorkflow();
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj'], $workflow['owner']);
        $verifiers = [
            $this->userWithRole('Verifier Admin', $workflow['unit']),
            $this->userWithRole('Verifier Finance', $workflow['unit']),
            $this->userWithRole('Verifier Substantive', $workflow['unit']),
        ];

        foreach ([VerificationStage::Administration, VerificationStage::Finance, VerificationStage::Substantive] as $index => $stage) {
            app(VerifyLpjAction::class)->handle($workflow['lpj']->fresh(), $verifiers[$index], [
                'stage' => $stage->value,
                'status' => VerificationStatus::Verified->value,
                'notes' => 'Checklist lengkap.',
                'checklist' => $this->verificationChecklist($version, $stage),
                'evidence_ids' => [$workflow['activity']->evidence()->value('id')],
            ]);
        }

        $approver = $this->userWithRole('Approver', $workflow['unit']);
        app(ApproveLpjAction::class)->handle($workflow['lpj']->fresh(), $approver, [
            'level' => ApprovalLevel::Approver->value,
            'decision' => ApprovalDecision::Approved->value,
        ]);

        return [...$workflow, 'version' => $version];
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
