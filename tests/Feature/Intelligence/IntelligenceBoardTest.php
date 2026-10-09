<?php

namespace Tests\Feature\Intelligence;

use App\Enums\AiReviewStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\AuditEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class IntelligenceBoardTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/intelligence')->assertRedirect('/login');
    }

    public function test_user_without_intelligence_permissions_is_forbidden(): void
    {
        $unit = Unit::factory()->create();
        $auditor = $this->userWithRole('Auditor', $unit);

        $this->actingAs($auditor)->get('/intelligence')->assertForbidden();
    }

    public function test_reviewer_can_open_board_and_only_see_their_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'SOBE Jawa Barat']);
        $otherUnit = Unit::factory()->create(['name' => 'SOBE Jawa Timur']);
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $requester = $this->userWithRole('Owner/PIC', $unit);
        $otherRequester = User::factory()->forUnit($otherUnit)->create();
        $visible = $this->review($unit, $requester, [
            'type' => 'RISK_REVIEW',
            'status' => AiReviewStatus::ReviewRequired,
            'confidence' => 0.82,
            'limitations' => 'Terbatas pada snapshot LPJ.',
            'output' => [
                'summary' => 'Ada satu risiko yang perlu ditinjau.',
                'risks' => ['Evidence terlambat.'],
                'recommendations' => ['Tetapkan checkpoint evidence.'],
                'sources' => ['LPJ v2'],
                'assumptions' => ['Seluruh transaksi telah tersinkron.'],
            ],
        ]);
        $this->review($otherUnit, $otherRequester);

        $this->actingAs($reviewer)->get('/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intelligence/index')
                ->where('scope.can_view_all_units', false)
                ->where('scope.label', 'SOBE Jawa Barat')
                ->where('capabilities.can_request', false)
                ->where('capabilities.can_review', true)
                ->where('summary.total', 1)
                ->where('summary.pending_review', 1)
                ->where('summary.average_confidence', 82)
                ->has('reviews.data', 1)
                ->where('reviews.data.0.id', $visible->id)
                ->where('reviews.data.0.output.summary', 'Ada satu risiko yang perlu ditinjau.')
                ->where('reviews.data.0.activity.unit.name', 'SOBE Jawa Barat')
                ->has('options.units', 0));
    }

    public function test_super_admin_can_filter_a_specific_unit_and_review_state(): void
    {
        $firstUnit = Unit::factory()->create(['name' => 'PB PERKEMI']);
        $secondUnit = Unit::factory()->create(['name' => 'SOBE Bali']);
        $admin = $this->userWithRole('Super Admin', $firstUnit);
        $firstRequester = User::factory()->forUnit($firstUnit)->create();
        $secondRequester = User::factory()->forUnit($secondUnit)->create();
        $this->review($firstUnit, $firstRequester, ['human_review_status' => 'PENDING']);
        $visible = $this->review($secondUnit, $secondRequester, [
            'type' => 'NARRATIVE',
            'status' => AiReviewStatus::Succeeded,
            'human_review_status' => 'ACCEPTED',
            'model' => 'grounded-model',
        ]);

        $query = http_build_query([
            'unit' => $secondUnit->id,
            'type' => 'NARRATIVE',
            'status' => 'SUCCEEDED',
            'review_status' => 'ACCEPTED',
            'model' => 'grounded-model',
        ]);

        $this->actingAs($admin)->get('/intelligence?'.$query)
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_all_units', true)
                ->where('scope.label', 'SOBE Bali')
                ->where('filters.unit', $secondUnit->id)
                ->where('summary.total', 1)
                ->has('reviews.data', 1)
                ->where('reviews.data.0.id', $visible->id));
    }

    public function test_local_user_cannot_probe_another_unit_filter(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);

        $this->actingAs($reviewer)
            ->get('/intelligence?unit='.$otherUnit->id)
            ->assertNotFound();
    }

    public function test_modified_review_requires_reason_and_persists_human_output(): void
    {
        $unit = Unit::factory()->create();
        $requester = $this->userWithRole('Owner/PIC', $unit);
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $review = $this->review($unit, $requester, [
            'status' => AiReviewStatus::ReviewRequired,
            'output' => [
                'summary' => 'Ringkasan awal.',
                'risks' => [],
                'recommendations' => ['Rekomendasi awal.'],
            ],
        ]);

        $this->actingAs($reviewer)->patch('/ai-reviews/'.$review->id.'/human-review', [
            'decision' => 'MODIFIED',
            'review_notes' => 'Rekomendasi disesuaikan dengan kapasitas unit.',
            'modified_summary' => 'Ringkasan telah diverifikasi manusia.',
            'modified_recommendations' => "Mulai dari satu dojo.\nEvaluasi setelah 30 hari.",
        ])->assertRedirect()->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame('MODIFIED', $review->human_review_status);
        $this->assertSame(AiReviewStatus::Succeeded, $review->status);
        $this->assertSame($reviewer->id, $review->reviewed_by);
        $this->assertSame('Rekomendasi disesuaikan dengan kapasitas unit.', $review->review_notes);
        $this->assertSame('Ringkasan telah diverifikasi manusia.', $review->reviewed_output['summary']);
        $this->assertSame(['Mulai dari satu dojo.', 'Evaluasi setelah 30 hari.'], $review->reviewed_output['recommendations']);

        $audit = AuditEvent::query()->where('event_type', 'ai.recommendation_reviewed')->firstOrFail();
        $this->assertSame(AiReviewStatus::Succeeded->value, $audit->to_status);
        $this->assertSame('MODIFIED', $audit->payload['review_decision']);
        $this->assertSame('Rekomendasi disesuaikan dengan kapasitas unit.', $audit->payload['review_notes']);
    }

    public function test_rejection_without_reason_has_user_visible_validation_error(): void
    {
        $unit = Unit::factory()->create();
        $requester = $this->userWithRole('Owner/PIC', $unit);
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $review = $this->review($unit, $requester, ['status' => AiReviewStatus::ReviewRequired]);

        $this->actingAs($reviewer)
            ->from('/intelligence')
            ->patch('/ai-reviews/'.$review->id.'/human-review', ['decision' => 'REJECTED'])
            ->assertRedirect('/intelligence')
            ->assertSessionHasErrors(['review_notes' => 'Alasan wajib diisi ketika rekomendasi diubah atau ditolak.']);

        $this->assertSame('PENDING', $review->fresh()->human_review_status);
    }

    public function test_rejected_output_is_cancelled_and_cannot_become_a_planning_draft(): void
    {
        $unit = Unit::factory()->create();
        $requester = $this->userWithRole('Owner/PIC', $unit);
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $review = $this->review($unit, $requester, ['status' => AiReviewStatus::ReviewRequired]);

        $this->actingAs($reviewer)->patch('/ai-reviews/'.$review->id.'/human-review', [
            'decision' => 'REJECTED',
            'review_notes' => 'Sumber belum cukup untuk menggunakan rekomendasi.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame(AiReviewStatus::Cancelled, $review->status);
        $this->assertSame('REJECTED', $review->human_review_status);
        $this->actingAs($requester)
            ->post('/ai-reviews/'.$review->id.'/planning-draft')
            ->assertForbidden();
    }

    public function test_cross_unit_review_is_not_disclosed(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $requester = User::factory()->forUnit($otherUnit)->create();
        $review = $this->review($otherUnit, $requester, ['status' => AiReviewStatus::ReviewRequired]);

        $this->actingAs($reviewer)->patch('/ai-reviews/'.$review->id.'/human-review', [
            'decision' => 'ACCEPTED',
        ])->assertNotFound();
    }

    private function review(Unit $unit, User $requester, array $attributes = []): AiReview
    {
        $activity = Activity::factory()->for($unit)->create();

        return AiReview::factory()
            ->for($activity)
            ->for($requester, 'requester')
            ->create([
                'status' => AiReviewStatus::Queued,
                'model' => 'local-rules-engine',
                'input_sources' => [
                    'activity' => ['id' => $activity->id, 'code' => $activity->code],
                ],
                'human_review_status' => 'PENDING',
                ...$attributes,
            ]);
    }
}
