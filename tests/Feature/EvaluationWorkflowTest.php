<?php

namespace Tests\Feature;

use App\Actions\SubmitLpjAction;
use App\Enums\EvaluationStatus;
use App\Enums\TaskStatus;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class EvaluationWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_pic_finalizes_versioned_kpi_results_and_substantive_verifier_accepts_them(): void
    {
        Notification::fake();
        $workflow = $this->completeWorkflow();
        $workflow['activity']->evaluations()->delete();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $verifier = $this->userWithRole('Verifier Substantive', $workflow['unit']);

        $response = $this->actingAs($workflow['owner'])->postJson(
            "/api/v1/activities/{$workflow['activity']->id}/evaluations",
            $this->evaluationPayload($evidence),
        );

        $response->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', EvaluationStatus::Submitted->value)
            ->assertJsonPath('data.formula_version', 'kpi-achievement-v1');
        $evaluation = Evaluation::query()->with('kpiDefinitions.result')->firstOrFail();
        $this->assertSame('105.56', $evaluation->score);
        $this->assertSame('90.0000', $evaluation->kpiDefinitions->first()->target);
        $this->assertSame('95.0000', $evaluation->kpiDefinitions->first()->result->actual);
        $this->assertSame('105.56', $evaluation->kpiDefinitions->first()->result->achievement_percent);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $workflow['activity']->id,
            'type' => 'REVIEW_EVALUATION',
            'status' => TaskStatus::Open->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $evaluation->id,
            'event_type' => 'evaluation.finalized',
        ]);

        $this->actingAs($workflow['owner'])
            ->post("/activities/{$workflow['activity']->id}/evaluations", $this->evaluationPayload($evidence))
            ->assertSessionHasErrors('evaluation');

        $this->actingAs($verifier)->patchJson("/api/v1/evaluations/{$evaluation->id}/review", [
            'decision' => 'ACCEPTED',
            'notes' => 'KPI, formula, dan bukti telah konsisten.',
        ])->assertOk()->assertJsonPath('data.status', EvaluationStatus::Accepted->value);

        $this->assertSame($verifier->id, $evaluation->fresh()->reviewed_by);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $workflow['activity']->id,
            'type' => 'COMPLETE_LPJ',
            'status' => TaskStatus::Open->value,
        ]);
        $version = app(SubmitLpjAction::class)->handle($workflow['lpj']->fresh(), $workflow['owner']);
        $this->assertSame($evaluation->id, $version->snapshot['evaluations'][0]['id']);
        $this->assertSame($evidence->id, $version->snapshot['evaluations'][0]['kpis'][0]['evidence']['id']);
        $this->actingAs($workflow['owner'])
            ->post("/activities/{$workflow['activity']->id}/evaluations", $this->evaluationPayload($evidence))
            ->assertSessionHasErrors('evaluation');
        $this->assertSame(1, $workflow['activity']->evaluations()->count());
    }

    public function test_clarification_and_finding_paths_preserve_each_evaluation_version(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->evaluations()->delete();
        $evidence = $workflow['activity']->evidence()->firstOrFail();
        $verifier = $this->userWithRole('Verifier Substantive', $workflow['unit']);

        $this->actingAs($workflow['owner'])
            ->post("/activities/{$workflow['activity']->id}/evaluations", $this->evaluationPayload($evidence))
            ->assertSessionHasNoErrors();
        $first = $workflow['activity']->evaluations()->firstOrFail();

        $this->actingAs($workflow['owner'])->patch("/evaluations/{$first->id}/review", [
            'decision' => 'ACCEPTED',
        ])->assertForbidden();
        $this->actingAs($verifier)->patch("/evaluations/{$first->id}/review", [
            'decision' => 'CLARIFICATION_REQUIRED',
            'notes' => 'Jelaskan metode pengukuran completion.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(EvaluationStatus::ClarificationRequired, $first->fresh()->status);

        $revisedPayload = $this->evaluationPayload($evidence);
        $revisedPayload['summary'] = 'Evaluasi versi dua dengan metode pengukuran yang diperjelas.';
        $this->actingAs($workflow['owner'])
            ->post("/activities/{$workflow['activity']->id}/evaluations", $revisedPayload)
            ->assertSessionHasNoErrors();
        $second = $workflow['activity']->evaluations()->latest('version')->firstOrFail();
        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertSame(EvaluationStatus::Superseded, $first->fresh()->status);

        $this->actingAs($verifier)->patch("/evaluations/{$second->id}/review", [
            'decision' => 'FINDING',
            'notes' => 'Bukti outcome belum menunjukkan periode pengukuran secara memadai.',
            'severity' => 'HIGH',
            'due_at' => now()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(EvaluationStatus::FindingOpen, $second->fresh()->status);
        $this->assertDatabaseHas('smart_tasks', [
            'activity_id' => $workflow['activity']->id,
            'assignee_id' => $workflow['owner']->id,
            'type' => 'RESOLVE_EVALUATION_FINDING',
            'priority' => 'HIGH',
            'status' => TaskStatus::Open->value,
        ]);
    }

    public function test_kpi_evidence_must_be_registered_and_belong_to_the_activity(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->evaluations()->delete();
        $other = $this->completeWorkflow(Unit::factory()->create());
        $foreignEvidence = $other['activity']->evidence()->firstOrFail();

        $this->actingAs($workflow['owner'])
            ->postJson("/api/v1/activities/{$workflow['activity']->id}/evaluations", $this->evaluationPayload($foreignEvidence))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kpis.0.evidence_id');

        $localEvidence = $workflow['activity']->evidence()->firstOrFail();
        $localEvidence->update(['scan_status' => 'UPLOADING']);
        $this->actingAs($workflow['owner'])
            ->postJson("/api/v1/activities/{$workflow['activity']->id}/evaluations", $this->evaluationPayload($localEvidence))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kpis.0.evidence_id');
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(Evidence $evidence): array
    {
        return [
            'summary' => 'Seluruh output utama kegiatan berhasil diselesaikan.',
            'outcomes' => 'Kompetensi peserta meningkat dan praktik baru mulai diterapkan.',
            'completion_percent' => 96,
            'satisfaction_score' => 4.6,
            'lessons_learned' => 'Registrasi perlu dibuka lebih awal.',
            'successful_practices' => 'Check-in digital mempercepat validasi kehadiran.',
            'improvement_areas' => 'Evidence outcome perlu dihimpun secara longitudinal.',
            'recommendations' => 'Gunakan evaluasi lanjutan setelah tiga puluh hari.',
            'kpis' => [[
                'position' => 0,
                'actual' => 95,
                'evidence_id' => $evidence->id,
                'notes' => 'Aktual dihitung dari agenda yang selesai.',
            ]],
        ];
    }
}
