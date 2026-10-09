<?php

namespace Tests\Feature\Evidence;

use App\Actions\ProcessEvidenceScanAction;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Jobs\ScanEvidenceFile;
use App\Models\Evidence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class EvidenceScanWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_upload_stores_private_file_as_uploading_and_dispatches_scan_job(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        Queue::fake([ScanEvidenceFile::class]);

        $response = $this->actingAs($workflow['owner'])->post("/activities/{$workflow['activity']->id}/evidence", [
            'type' => 'REPORT',
            'title' => 'Laporan dalam pemeriksaan',
            'access_classification' => 'RESTRICTED',
            'file' => UploadedFile::fake()->createWithContent('laporan.pdf', "%PDF-1.4\nLaporan bersih"),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $evidence = Evidence::query()->where('title', 'Laporan dalam pemeriksaan')->firstOrFail();
        $this->assertSame(EvidenceScanStatus::Uploading, $evidence->scan_status);
        $this->assertSame('RESTRICTED', $evidence->access_classification);
        Storage::disk('local')->assertExists($evidence->path);
        Queue::assertPushed(ScanEvidenceFile::class, fn (ScanEvidenceFile $job): bool => $job->evidenceId === $evidence->id);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evidence->id,
            'event_type' => 'evidence.uploaded',
            'to_status' => EvidenceScanStatus::Uploading->value,
        ]);
    }

    public function test_clean_file_becomes_linked_and_records_scan_audit(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $evidence = $this->quarantinedEvidence($workflow['activity']->id, $workflow['owner']->id, "%PDF-1.4\nBukti bersih", 'bukti.pdf', 'application/pdf');

        $result = $this->app->make(ProcessEvidenceScanAction::class)->handle($evidence);

        $this->assertSame(EvidenceScanStatus::Registered, $result->scan_status);
        $this->assertSame('LINKED', $result->lifecycleStatus());
        $this->assertSame('application/pdf', $result->detected_mime_type);
        $this->assertSame('SIGNATURE', $result->scan_engine);
        $this->assertNotNull($result->scanned_at);
        $this->assertNotNull($result->linked_at);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evidence->id,
            'event_type' => 'evidence.scan_passed',
            'from_status' => EvidenceScanStatus::Uploading->value,
            'to_status' => EvidenceScanStatus::Registered->value,
        ]);
    }

    public function test_malware_signature_is_quarantined_and_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        $evidence = $this->quarantinedEvidence($workflow['activity']->id, $workflow['owner']->id, $eicar, 'indikator.csv', 'text/plain');

        $result = $this->app->make(ProcessEvidenceScanAction::class)->handle($evidence);
        $downloadUrl = URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), ['evidence' => $result]);

        $this->assertSame(EvidenceScanStatus::ScanFailed, $result->scan_status);
        $this->assertSame('SCAN_FAILED', $result->lifecycleStatus());
        $this->assertNull($result->linked_at);
        $this->actingAs($workflow['owner'])->get($downloadUrl)->assertStatus(423);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evidence->id,
            'event_type' => 'evidence.scan_failed',
            'to_status' => EvidenceScanStatus::ScanFailed->value,
        ]);
    }

    public function test_review_is_blocked_until_scan_passes(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $evidence = $this->quarantinedEvidence($workflow['activity']->id, $workflow['owner']->id, "%PDF-1.4\nMenunggu scan", 'menunggu.pdf', 'application/pdf');
        $reviewer = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Verified->value,
        ])->assertSessionHasErrors('status');

        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->fresh()->review_status);
    }

    public function test_worker_rejects_file_that_exceeds_the_scan_size_policy(): void
    {
        Storage::fake('local');
        config(['evidence.max_size_kb' => 1]);
        $workflow = $this->completeWorkflow();
        $contents = str_repeat('A', 2048);
        $evidence = $this->quarantinedEvidence($workflow['activity']->id, $workflow['owner']->id, $contents, 'terlalu-besar.pdf', 'application/pdf');

        $result = $this->app->make(ProcessEvidenceScanAction::class)->handle($evidence);

        $this->assertSame(EvidenceScanStatus::ScanFailed, $result->scan_status);
        $this->assertSame('SIZE-POLICY', $result->scan_engine);
        $this->assertSame('Ukuran berkas melampaui batas kebijakan evidence.', $result->scan_notes);
    }

    public function test_download_requires_valid_signature_and_registered_scan(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();

        $this->actingAs($workflow['owner'])->get("/evidence/{$evidence->id}/download")->assertForbidden();

        $signedUrl = URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), ['evidence' => $evidence]);
        $this->actingAs($workflow['owner'])->get($signedUrl)->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evidence->id,
            'event_type' => 'evidence.accessed',
        ]);
    }

    public function test_pending_clean_evidence_cannot_be_replaced(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();

        $this->actingAs($workflow['owner'])->post("/activities/{$workflow['activity']->id}/evidence", [
            'type' => 'REPORT',
            'title' => 'Pengganti tidak sah',
            'supersedes_id' => $evidence->id,
            'file' => UploadedFile::fake()->createWithContent('pengganti.pdf', "%PDF-1.4\nPengganti"),
        ])->assertSessionHasErrors('supersedes_id');

        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->fresh()->review_status);
        $this->assertSame(0, $evidence->revisions()->count());
    }

    public function test_api_upload_uses_the_same_quarantine_pipeline(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        Queue::fake([ScanEvidenceFile::class]);
        Sanctum::actingAs($workflow['owner']);

        $response = $this->post('/api/v1/activities/'.$workflow['activity']->id.'/evidence', [
            'type' => 'ATTENDANCE',
            'title' => 'Daftar hadir API',
            'file' => UploadedFile::fake()->createWithContent('kehadiran.pdf', "%PDF-1.4\nDaftar hadir"),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.scan_status', EvidenceScanStatus::Uploading->value);
        $evidence = Evidence::query()->where('title', 'Daftar hadir API')->firstOrFail();
        Queue::assertPushed(ScanEvidenceFile::class, fn (ScanEvidenceFile $job): bool => $job->evidenceId === $evidence->id);
    }

    public function test_evidence_center_is_scoped_to_the_users_unit(): void
    {
        $visibleWorkflow = $this->completeWorkflow();
        $hiddenWorkflow = $this->completeWorkflow();
        $visibleWorkflow['activity']->update(['title' => 'Kegiatan unit pengguna']);
        $hiddenWorkflow['activity']->update(['title' => 'Kegiatan unit lain']);

        $this->actingAs($visibleWorkflow['owner'])
            ->get('/workspace/evidence')
            ->assertInertia(fn (Assert $page) => $page
                ->component('evidence/index')
                ->where('summary.total', 1)
                ->has('evidence', 1)
                ->where('evidence.0.activity.title', 'Kegiatan unit pengguna'));
    }

    private function quarantinedEvidence(string $activityId, string $uploaderId, string $contents, string $originalName, string $mimeType): Evidence
    {
        $path = 'evidence/'.$activityId.'/'.$originalName;
        Storage::disk('local')->put($path, $contents);

        return Evidence::factory()->create([
            'activity_id' => $activityId,
            'uploaded_by' => $uploaderId,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'detected_mime_type' => null,
            'size' => mb_strlen($contents, '8bit'),
            'sha256' => hash('sha256', $contents),
            'scan_status' => EvidenceScanStatus::Uploading,
            'scan_engine' => null,
            'scan_notes' => null,
            'scanned_at' => null,
            'linked_at' => null,
        ]);
    }
}
