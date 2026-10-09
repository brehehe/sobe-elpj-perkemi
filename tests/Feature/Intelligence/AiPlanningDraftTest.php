<?php

namespace Tests\Feature\Intelligence;

use App\Actions\RequestAiReviewAction;
use App\Models\Plan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AiPlanningDraftTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_accepted_recommendation_creates_an_editable_planning_draft_with_lineage(): void
    {
        $workflow = $this->completeWorkflow();
        $reviewer = $this->userWithRole('Verifier Substantive', $workflow['unit']);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');

        $this->actingAs($reviewer)
            ->patch("/ai-reviews/{$review->id}/human-review", ['decision' => 'ACCEPTED', 'review_notes' => 'Layak menjadi bahan planning berikutnya.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($workflow['owner'])
            ->post("/ai-reviews/{$review->id}/planning-draft")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = Plan::query()->where('source_ai_review_id', $review->id)->firstOrFail();
        $version = $draft->currentVersionRecord;

        $this->assertSame('DRAFT', $draft->status->value);
        $this->assertSame(0, $draft->current_version);
        $this->assertSame($workflow['owner']->id, $draft->owner_id);
        $this->assertSame(0, $version->version);
        $this->assertSame('DRAFT', $version->status->value);
        $this->assertFalse($version->is_immutable);
        $this->assertSame($review->id, $version->source_context['ai_review_id']);
        $this->assertSame($workflow['activity']->id, $version->source_context['source_activity_id']);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $draft->id,
            'event_type' => 'plan.ai_draft_created',
        ]);

        $this->actingAs($workflow['owner'])->post("/ai-reviews/{$review->id}/planning-draft")->assertRedirect();
        $this->assertSame(1, Plan::query()->where('source_ai_review_id', $review->id)->count());
    }

    public function test_ai_planning_draft_still_requires_formal_submission_and_preserves_source_context(): void
    {
        $workflow = $this->completeWorkflow();
        $reviewer = $this->userWithRole('Verifier Substantive', $workflow['unit']);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');
        $this->actingAs($reviewer)->patch("/ai-reviews/{$review->id}/human-review", ['decision' => 'ACCEPTED']);
        $this->actingAs($workflow['owner'])->post("/ai-reviews/{$review->id}/planning-draft");
        $draft = Plan::query()->where('source_ai_review_id', $review->id)->firstOrFail();
        $draftVersion = $draft->currentVersionRecord;

        $this->actingAs($workflow['owner'])->post("/planning/{$draft->id}/versions", [
            'mandate_id' => $draft->mandate_id,
            'code' => $draft->code,
            'activity_type' => $draftVersion->activity_type,
            'objective' => $draftVersion->objective,
            'output' => $draftVersion->output,
            'outcome' => $draftVersion->outcome,
            'target_participants' => $draftVersion->target_participants,
            'kpis' => $draftVersion->kpis,
            'schedule' => [],
            'resources' => $draftVersion->resources,
            'risks' => $draftVersion->risks,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $submitted = $draft->fresh();

        $this->assertSame('SUBMITTED', $submitted->status->value);
        $this->assertSame(1, $submitted->current_version);
        $this->assertTrue($submitted->currentVersionRecord->is_immutable);
        $this->assertSame($review->id, $submitted->currentVersionRecord->source_context['ai_review_id']);
    }

    public function test_unreviewed_recommendation_and_unauthorized_reviewer_cannot_create_a_planning_draft(): void
    {
        $workflow = $this->completeWorkflow();
        $reviewer = $this->userWithRole('Verifier Substantive', $workflow['unit']);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');

        $this->actingAs($workflow['owner'])->post("/ai-reviews/{$review->id}/planning-draft")->assertForbidden();

        $this->actingAs($reviewer)->patch("/ai-reviews/{$review->id}/human-review", ['decision' => 'ACCEPTED']);
        $this->actingAs($reviewer)->post("/ai-reviews/{$review->id}/planning-draft")->assertForbidden();

        $this->assertDatabaseMissing('plans', ['source_ai_review_id' => $review->id]);
    }
}
