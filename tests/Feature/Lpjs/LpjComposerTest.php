<?php

namespace Tests\Feature\Lpjs;

use App\Actions\SubmitLpjAction;
use App\Enums\LpjStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LpjComposerTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_owner_saves_narratives_and_composer_becomes_ready_to_submit(): void
    {
        $workflow = $this->completeWorkflow();
        $payload = $this->narratives(['draft_revision' => 0]);

        $response = $this->actingAs($workflow['owner'])
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $payload);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lpjs', [
            'id' => $workflow['lpj']->id,
            'status' => LpjStatus::ReadyToSubmit->value,
            'executive_summary' => $payload['executive_summary'],
            'draft_revision' => 1,
            'draft_updated_by' => $workflow['owner']->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $workflow['lpj']->id,
            'event_type' => 'lpj.draft_updated',
        ]);

        $this->actingAs($workflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('lpjs/show')
                ->where('composer.ready', true)
                ->where('composer.percentage', 100)
                ->has('composer.modules', 8)
                ->where('lpj.draft_revision', 1));
    }

    public function test_incomplete_narrative_remains_a_visible_submission_blocker(): void
    {
        $workflow = $this->completeWorkflow();
        $payload = $this->narratives([
            'executive_summary' => '',
            'draft_revision' => 0,
        ]);

        $this->actingAs($workflow['owner'])
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(LpjStatus::Draft, $workflow['lpj']->fresh()->status);
        $this->actingAs($workflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('composer.ready', false)
                ->where('composer.errors.executive_summary', 'Ringkasan eksekutif wajib dilengkapi pada composer.'));
    }

    public function test_stale_draft_revision_does_not_overwrite_a_newer_save(): void
    {
        $workflow = $this->completeWorkflow();
        $firstPayload = $this->narratives([
            'executive_summary' => 'Ringkasan dari tab pertama.',
            'draft_revision' => 0,
        ]);
        $this->actingAs($workflow['owner'])
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $firstPayload)
            ->assertSessionHasNoErrors();

        $this->actingAs($workflow['owner'])
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $this->narratives([
                'executive_summary' => 'Ringkasan dari tab lama.',
                'draft_revision' => 0,
            ]))
            ->assertSessionHasErrors('draft_revision');

        $lpj = $workflow['lpj']->fresh();
        $this->assertSame(1, $lpj->draft_revision);
        $this->assertSame('Ringkasan dari tab pertama.', $lpj->executive_summary);
    }

    public function test_user_without_submit_permission_cannot_update_composer(): void
    {
        $workflow = $this->completeWorkflow();
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $this->actingAs($verifier)
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $this->narratives(['draft_revision' => 0]))
            ->assertForbidden();

        $this->assertSame(0, $workflow['lpj']->fresh()->draft_revision);
    }

    public function test_submit_snapshot_contains_narratives_template_and_shared_precheck(): void
    {
        $workflow = $this->completeWorkflow();
        $payload = $this->narratives([
            'executive_summary' => 'Ringkasan final yang dibekukan.',
            'draft_revision' => 0,
        ]);
        $this->actingAs($workflow['owner'])
            ->patch("/lpjs/{$workflow['lpj']->id}/draft", $payload)
            ->assertSessionHasNoErrors();

        $version = app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);

        $this->assertSame('Ringkasan final yang dibekukan.', $version->snapshot['narratives']['executive_summary']);
        $this->assertSame('lpj-precheck-v3', $version->snapshot['composer']['policy_version']);
        $this->assertSame(100, $version->snapshot['composer']['completeness_percentage']);
        $this->assertCount(8, $version->snapshot['composer']['modules']);
        $this->assertNotEmpty($version->snapshot['system_checks']);
    }

    public function test_submit_is_rejected_when_required_composer_narrative_is_missing(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['lpj']->update(['follow_up_plan' => null]);

        try {
            app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Rencana tindak lanjut wajib dilengkapi pada composer.',
                $exception->errors()['follow_up_plan'][0],
            );
        }

        $this->assertSame(0, $workflow['lpj']->versions()->count());
    }

    public function test_authorized_user_can_export_an_audited_private_html_draft(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->update(['code' => 'ACT-EKSPOR-01', 'title' => 'Lokakarya Tata Kelola']);
        $workflow['lpj']->update([
            'status' => LpjStatus::ReadyToSubmit,
            'draft_revision' => 2,
            'executive_summary' => 'Ringkasan draf yang dapat diperiksa sebelum pengajuan.',
        ]);

        $response = $this->actingAs($workflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}/draft-export");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment; filename=draft-lpj-act-ekspor-01-r2.html', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('DRAF · BUKAN DOKUMEN FINAL', $content);
        $this->assertStringContainsString('ACT-EKSPOR-01', $content);
        $this->assertStringContainsString('Ringkasan draf yang dapat diperiksa sebelum pengajuan.', $content);
        $this->assertStringContainsString('lpj-precheck-v3', $content);
        $this->assertStringNotContainsString('evidence/'.$workflow['activity']->id, $content);

        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $workflow['lpj']->id,
            'actor_id' => $workflow['owner']->id,
            'event_type' => 'lpj.draft_exported',
            'authority' => 'lpjs.view',
            'object_version' => 'draft-r2',
        ]);
    }

    public function test_user_cannot_export_another_units_draft(): void
    {
        $workflow = $this->completeWorkflow();
        $otherWorkflow = $this->completeWorkflow();

        $this->actingAs($otherWorkflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}/draft-export")
            ->assertNotFound();
    }

    public function test_draft_export_is_blocked_after_submission(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['lpj']->update(['status' => LpjStatus::Submitted]);

        $this->actingAs($workflow['owner'])
            ->get("/lpjs/{$workflow['lpj']->id}/draft-export")
            ->assertConflict();

        $this->assertDatabaseMissing('audit_events', [
            'auditable_id' => $workflow['lpj']->id,
            'event_type' => 'lpj.draft_exported',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function narratives(array $overrides = []): array
    {
        return array_merge([
            'executive_summary' => 'Kegiatan selesai sesuai mandat dan mencapai tujuan utama.',
            'implementation_narrative' => 'Pelaksanaan berjalan sesuai agenda dengan partisipasi aktif peserta.',
            'outcome_narrative' => 'Outcome awal mendukung peningkatan kapasitas organisasi.',
            'financial_narrative' => 'Realisasi bersumber dari transaksi resmi yang telah direkonsiliasi.',
            'constraints' => 'Kendala dokumentasi ditangani melalui checkpoint harian.',
            'follow_up_plan' => 'Tindak lanjut dilakukan melalui pemantauan dan evaluasi berkala.',
        ], $overrides);
    }
}
