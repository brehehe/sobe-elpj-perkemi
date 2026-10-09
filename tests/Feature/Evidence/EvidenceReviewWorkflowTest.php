<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceReviewStatus;
use App\Models\AuditEvent;
use App\Models\Evidence;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class EvidenceReviewWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_authorized_verifier_reviews_evidence_and_creates_an_audit_event(): void
    {
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $reviewer = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $response = $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Verified->value,
            'notes' => 'Dokumen terbaca dan sesuai sumber.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EvidenceReviewStatus::Verified, $evidence->fresh()->review_status);
        $this->assertSame($reviewer->id, $evidence->fresh()->reviewed_by);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evidence->id,
            'event_type' => 'evidence.reviewed',
            'from_status' => EvidenceReviewStatus::Pending->value,
            'to_status' => EvidenceReviewStatus::Verified->value,
        ]);
        $this->assertSame(1, AuditEvent::query()->where('auditable_id', $evidence->id)->count());
    }

    public function test_uploader_cannot_review_their_own_evidence(): void
    {
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();

        $this->actingAs($workflow['owner'])->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Verified->value,
        ])->assertForbidden();

        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->fresh()->review_status);
    }

    public function test_cross_unit_evidence_review_returns_not_found(): void
    {
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $reviewer = $this->userWithRole('Verifier Admin', Unit::factory()->create());

        $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Rejected->value,
            'notes' => 'Tidak relevan.',
        ])->assertNotFound();

        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->fresh()->review_status);
    }

    public function test_rejected_evidence_requires_a_review_note(): void
    {
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $reviewer = $this->userWithRole('Verifier Finance', $workflow['unit']);

        $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Rejected->value,
        ])->assertSessionHasErrors('notes');

        $this->assertSame(EvidenceReviewStatus::Pending, $evidence->fresh()->review_status);
    }

    public function test_replacing_evidence_supersedes_the_previous_version(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $reviewer = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => EvidenceReviewStatus::Rejected->value,
            'notes' => 'Dokumen perlu diperbarui.',
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($workflow['owner'])->post("/activities/{$workflow['activity']->id}/evidence", [
            'type' => 'REPORT',
            'title' => 'Laporan kegiatan diperbarui',
            'supersedes_id' => $evidence->id,
            'access_classification' => 'INTERNAL',
            'file' => UploadedFile::fake()->createWithContent('laporan-baru.pdf', "%PDF-1.4\nLaporan baru"),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EvidenceReviewStatus::Superseded, $evidence->fresh()->review_status);
        $replacement = Evidence::query()->where('supersedes_id', $evidence->id)->firstOrFail();
        $this->assertSame(2, $replacement->version);
        $this->assertSame(EvidenceReviewStatus::Pending, $replacement->review_status);
    }
}
