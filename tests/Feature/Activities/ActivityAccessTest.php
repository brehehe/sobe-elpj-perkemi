<?php

namespace Tests\Feature\Activities;

use App\Models\Activity;
use App\Models\Evidence;
use App\Models\Lpj;
use App\Models\Mandate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ActivityAccessTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_user_cannot_resolve_activity_from_another_unit(): void
    {
        $first = $this->completeWorkflow();
        $second = $this->completeWorkflow();

        $this->actingAs($first['owner'])->get('/activities/'.$second['activity']->id)->assertNotFound();
    }

    public function test_owner_can_create_activity_and_a_draft_lpj_is_created(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $owner->id]);
        ['budget' => $budget] = $this->approvedSources($unit, $mandate, $owner);

        $response = $this->actingAs($owner)->post('/activities', [
            'budget_id' => $budget->id,
            'code' => 'KGT-TEST-001',
            'title' => 'Kegiatan Pengujian',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $activity = Activity::query()->where('code', 'KGT-TEST-001')->firstOrFail();
        $response->assertRedirect(route('activities.show', $activity));
        $this->assertDatabaseHas('lpjs', ['activity_id' => $activity->id, 'owner_id' => $owner->id, 'status' => 'DRAFT']);
        $this->assertSame($budget->id, $activity->budget_id);
        $this->assertSame($budget->plan_id, $activity->plan_id);
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $activity->id, 'event_type' => 'activity.created']);
    }

    public function test_source_records_can_be_added_and_private_evidence_access_is_audited(): void
    {
        Storage::fake('local');
        $workflow = $this->completeWorkflow();
        $owner = $workflow['owner'];
        $activity = $workflow['activity'];

        $this->actingAs($owner)->post("/activities/{$activity->id}/participants", [
            'name' => 'Peserta Baru', 'attendance_status' => 'ATTENDED',
        ])->assertSessionHasNoErrors();
        $existingEvidence = $activity->evidence()->firstOrFail();
        $this->actingAs($owner)->post("/activities/{$activity->id}/evaluations", [
            'summary' => 'Evaluasi baru yang terdokumentasi.',
            'outcomes' => 'Target substantif tercapai.',
            'completion_percent' => 98,
            'satisfaction_score' => 4.6,
            'lessons_learned' => 'Registrasi perlu dimulai lebih awal.',
            'successful_practices' => 'Check-in digital berjalan baik.',
            'improvement_areas' => 'Rekonsiliasi harian perlu diperkuat.',
            'recommendations' => 'Pertahankan pola pelaksanaan hybrid.',
            'kpis' => [[
                'position' => 0,
                'actual' => 95,
                'evidence_id' => $existingEvidence->id,
                'notes' => 'Agenda inti selesai.',
            ]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/activities/{$activity->id}/evidence", [
            'type' => 'REPORT', 'title' => 'Bukti baru', 'file' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti kegiatan"),
        ])->assertSessionHasNoErrors();

        $evidence = Evidence::query()->where('title', 'Bukti baru')->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
        $downloadUrl = URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), ['evidence' => $evidence]);
        $this->actingAs($owner)->get($downloadUrl)->assertOk();
        $reviewer = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $this->actingAs($reviewer)->patch("/evidence/{$evidence->id}/review", [
            'status' => 'REJECTED',
            'notes' => 'Versi awal perlu diganti.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/activities/{$activity->id}/evidence", [
            'type' => 'REPORT',
            'title' => 'Bukti baru diperbarui',
            'supersedes_id' => $evidence->id,
            'access_classification' => 'RESTRICTED',
            'file' => UploadedFile::fake()->createWithContent('bukti-v2.pdf', "%PDF-1.4\nBukti kegiatan versi dua"),
        ])->assertSessionHasNoErrors();
        $replacement = Evidence::query()->where('supersedes_id', $evidence->id)->firstOrFail();
        $this->assertSame(2, $replacement->version);
        $this->assertSame('RESTRICTED', $replacement->access_classification);
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $evidence->id, 'event_type' => 'evidence.accessed']);
        $this->assertSame(2, $activity->participants()->count());
        $this->assertSame(2, $activity->evaluations()->count());
    }

    public function test_owner_can_update_and_delete_an_unused_planned_activity(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $owner->id]);
        ['plan' => $plan, 'budget' => $budget] = $this->approvedSources($unit, $mandate, $owner);
        $activity = Activity::factory()->create([
            'unit_id' => $unit->id,
            'mandate_id' => $mandate->id,
            'plan_id' => $plan->id,
            'budget_id' => $budget->id,
            'pic_id' => $owner->id,
            'status' => 'PLANNED',
        ]);
        $lpj = Lpj::factory()->create([
            'activity_id' => $activity->id,
            'owner_id' => $owner->id,
            'status' => 'DRAFT',
            'current_version' => 0,
        ]);

        $this->actingAs($owner)->get("/activities/{$activity->id}/edit")->assertOk();
        $this->actingAs($owner)->patch("/activities/{$activity->id}", [
            'title' => 'Kegiatan diperbarui',
            'description' => 'Metadata pelaksanaan diperbarui.',
            'location' => 'Bandung',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertRedirect(route('activities.show', $activity))->assertSessionHasNoErrors();
        $this->assertSame('Kegiatan diperbarui', $activity->fresh()->title);

        $this->actingAs($owner)->delete("/activities/{$activity->id}")->assertRedirect('/activities')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('lpjs', ['id' => $lpj->id]);
    }
}
