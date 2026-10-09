<?php

namespace Tests\Feature;

use App\Enums\LpjStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\LpjVersion;
use App\Models\Participant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ActivityIntelligenceTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/activity-intelligence')->assertRedirect('/login');
    }

    public function test_user_without_activity_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/activity-intelligence')->assertForbidden();
    }

    public function test_local_user_only_sees_verified_lpjs_from_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $visible = $this->verifiedActivity($unit, $owner, 'PEMBINAAN', 18, 88, 3);
        $unverified = $this->completeWorkflow($unit, $owner);
        $unverifiedSnapshot = $unverified['activity']->plan_snapshot;
        $unverifiedSnapshot['activity_type'] = 'PEMBINAAN';
        $unverified['activity']->update(['plan_snapshot' => $unverifiedSnapshot, 'code' => 'KGT-UNVERIFIED']);
        $otherUnit = Unit::factory()->create();
        $otherOwner = $this->userWithRole('Owner/PIC', $otherUnit);
        $this->verifiedActivity($otherUnit, $otherOwner, 'PEMBINAAN', 40, 95, 2);

        $this->actingAs($owner)->get('/activity-intelligence?type=PEMBINAAN&period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('activity-intelligence/index')
                ->where('scope.can_view_across_units', false)
                ->where('scope.selected_unit.id', $unit->id)
                ->where('eligibility.comparable_count', 1)
                ->where('eligibility.is_sufficient', false)
                ->where('eligibility.missing_count', 2)
                ->has('cohort', 1)
                ->where('cohort.0.code', $visible->code)
                ->has('scenarios', 0));

        $this->actingAs($owner)->get('/activity-intelligence?unit='.$otherUnit->id)->assertNotFound();
    }

    public function test_three_verified_comparable_activities_produce_a_baseline_and_scenarios(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 10, 80, 6);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 20, 90, 4);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 30, 100, 2);

        $this->actingAs($owner)->get('/activity-intelligence?type=PENATARAN&period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'PENATARAN')
                ->where('eligibility.comparable_count', 3)
                ->where('eligibility.is_sufficient', true)
                ->where('metrics.median_participants', 20)
                ->where('metrics.outcome_index', 90)
                ->has('trend', 3)
                ->has('cohort', 3)
                ->has('scenarios', 3)
                ->where('methodology.rule_version', 'activity-intelligence-rules-v1'));
    }

    public function test_pb_verifier_can_analyze_a_selected_unit_and_descendants(): void
    {
        $root = Unit::factory()->create(['name' => 'PB Nasional']);
        $province = Unit::factory()->create(['parent_id' => $root->id, 'name' => 'Pengprov']);
        $branch = Unit::factory()->create(['parent_id' => $province->id, 'name' => 'Pengkot']);
        $provinceOwner = $this->userWithRole('Owner/PIC', $province);
        $branchOwner = $this->userWithRole('Owner/PIC', $branch);
        $this->verifiedActivity($province, $provinceOwner, 'UJIAN', 20, 84, 3);
        $this->verifiedActivity($branch, $branchOwner, 'UJIAN', 24, 88, 2);
        $pbVerifier = $this->userWithRole('PB Verifier', $root);

        $this->actingAs($pbVerifier)->get('/activity-intelligence?type=UJIAN&period=all&unit='.$province->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_across_units', true)
                ->where('scope.selected_unit.id', $province->id)
                ->where('scope.unit_count', 2)
                ->where('eligibility.comparable_count', 2)
                ->has('cohort', 2)
                ->where('cohort.0.url', fn (string $url): bool => str_starts_with($url, '/lpjs/')));
    }

    public function test_next_activity_route_uses_the_same_governed_cohort_in_next_mode(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $this->verifiedActivity($unit, $owner, 'RAPAT', 12, 82, 3);

        $this->actingAs($owner)->get('/next-activity?type=RAPAT&period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('activity-intelligence/index')
                ->where('mode', 'NEXT_ACTIVITY')
                ->where('filters.type', 'RAPAT')
                ->where('eligibility.comparable_count', 1));
    }

    public function test_invalid_taxonomy_filter_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);

        $this->actingAs($owner)
            ->from('/activity-intelligence')
            ->get('/activity-intelligence?type=UNTRUSTED')
            ->assertRedirect('/activity-intelligence')
            ->assertSessionHasErrors(['type' => 'Jenis kegiatan tidak dikenali.']);
    }

    public function test_insufficient_cohort_cannot_be_recorded_as_a_recommendation(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $this->verifiedActivity($unit, $owner, 'UJIAN', 18, 88, 2);

        $this->actingAs($owner)
            ->from('/next-activity?type=UJIAN&period=all')
            ->post('/activity-intelligence/recommendations', ['type' => 'UJIAN', 'period' => 'all'])
            ->assertRedirect('/next-activity?type=UJIAN&period=all')
            ->assertSessionHasErrors('recommendation');

        $this->assertDatabaseCount('ai_reviews', 0);
    }

    public function test_sample_count_alone_does_not_bypass_quality_eligibility(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $first = $this->verifiedActivity($unit, $owner, 'PEMBINAAN', 14, 84, 5);
        $second = $this->verifiedActivity($unit, $owner, 'PEMBINAAN', 18, 88, 3);
        $this->verifiedActivity($unit, $owner, 'PEMBINAAN', 22, 92, 1);
        $first->evaluations()->delete();
        $second->evaluations()->delete();

        $this->actingAs($owner)->get('/activity-intelligence?type=PEMBINAAN&period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.comparable_count', 3)
                ->where('eligibility.missing_count', 0)
                ->where('eligibility.is_sufficient', false)
                ->where('eligibility.blocking_reasons', fn ($reasons): bool => collect($reasons)->contains('Cakupan evaluasi diterima'))
                ->has('scenarios', 0));

        $this->actingAs($owner)
            ->post('/activity-intelligence/recommendations', ['type' => 'PEMBINAAN', 'period' => 'all'])
            ->assertSessionHasErrors('recommendation');
    }

    public function test_eligible_cohort_is_persisted_reviewed_and_converted_to_a_traceable_planning_draft(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $this->verifiedActivity($unit, $owner, 'UJIAN', 10, 80, 6);
        $this->verifiedActivity($unit, $owner, 'UJIAN', 20, 90, 4);
        $this->verifiedActivity($unit, $owner, 'UJIAN', 30, 96, 2);
        $payload = ['type' => 'UJIAN', 'period' => 'all'];

        $this->actingAs($owner)
            ->post('/activity-intelligence/recommendations', $payload)
            ->assertRedirect('/intelligence?type=NEXT_ACTIVITY')
            ->assertSessionHas('success');

        $review = AiReview::query()->where('type', 'NEXT_ACTIVITY')->firstOrFail();
        $this->assertSame('REVIEW_REQUIRED', $review->status->value);
        $this->assertSame('PENDING', $review->human_review_status);
        $this->assertSame('lpj-intelligence-v1', $review->prompt_version);
        $this->assertSame('UJIAN', data_get($review->input_sources, 'cohort.activity_type'));
        $this->assertCount(3, data_get($review->input_sources, 'cohort.records'));
        $this->assertCount(3, data_get($review->output, 'scenarios'));
        $this->assertSame(20, data_get($review->output, 'planning_baseline.target_participants'));
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $review->activity_id,
            'event_type' => 'ai.next_activity_recommendation_queued',
        ]);

        $this->actingAs($owner)->post('/activity-intelligence/recommendations', $payload)->assertRedirect('/intelligence?type=NEXT_ACTIVITY');
        $this->assertDatabaseCount('ai_reviews', 1);

        $reviewer = $this->userWithRole('Verifier Substantive', $unit);
        $this->actingAs($reviewer)->patch('/ai-reviews/'.$review->id.'/human-review', [
            'decision' => 'ACCEPTED',
            'review_notes' => 'Baseline, sumber cohort, dan kontrol telah diperiksa.',
        ])->assertRedirect();

        $this->assertSame('SUCCEEDED', $review->fresh()->status->value);

        $this->actingAs($owner)->post('/ai-reviews/'.$review->id.'/planning-draft')
            ->assertRedirectContains('/planning?focus=');

        $draft = $review->fresh()->planningDraft()->with('currentVersionRecord')->firstOrFail();
        $this->assertSame('DRAFT', $draft->status->value);
        $this->assertSame('UJIAN', $draft->currentVersionRecord->activity_type);
        $this->assertSame(20, $draft->currentVersionRecord->target_participants);
        $this->assertSame($review->id, data_get($draft->currentVersionRecord->source_context, 'ai_review_id'));
    }

    public function test_next_activity_recommendation_uses_openai_queue_and_preserves_governed_baseline(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'test-model');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_next_activity',
                'status' => 'completed',
                'usage' => ['input_tokens' => 250, 'output_tokens' => 80, 'total_tokens' => 330],
                'output' => [['content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'Pengayaan skenario berdasarkan cohort terverifikasi.',
                        'risks' => ['Kapasitas verifier perlu dikonfirmasi.'],
                        'recommendations' => ['Gunakan checkpoint evidence harian.'],
                        'sources' => ['KGT cohort terverifikasi'],
                        'assumptions' => ['Taxonomy dan periode tetap sebanding.'],
                        'comparable_factors' => ['peserta', 'outcome', 'biaya'],
                        'normalization' => 'Median cohort tervalidasi.',
                        'confidence' => 0.84,
                        'limitations' => 'Tetap memerlukan keputusan manusia.',
                    ], JSON_THROW_ON_ERROR),
                ]]]],
            ]),
        ]);
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 10, 80, 6);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 20, 90, 4);
        $this->verifiedActivity($unit, $owner, 'PENATARAN', 30, 96, 2);

        $this->actingAs($owner)->post('/activity-intelligence/recommendations', [
            'type' => 'PENATARAN',
            'period' => 'all',
        ])->assertRedirect('/intelligence?type=NEXT_ACTIVITY');

        $review = AiReview::query()->where('type', 'NEXT_ACTIVITY')->sole();
        $this->assertSame('REVIEW_REQUIRED', $review->status->value);
        $this->assertSame('test-model', $review->model);
        $this->assertSame('Pengayaan skenario berdasarkan cohort terverifikasi.', data_get($review->output, 'summary'));
        $this->assertCount(3, data_get($review->output, 'scenarios'));
        $this->assertSame(20, data_get($review->output, 'planning_baseline.target_participants'));
        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'model') === 'test-model'
            && str_contains((string) data_get($request->data(), 'input.1.content'), 'NEXT_ACTIVITY')
            && ! str_contains((string) data_get($request->data(), 'input.1.content'), '_fallback_output'));
    }

    private function verifiedActivity(Unit $unit, User $owner, string $type, int $participants, int $outcome, int $monthsAgo): Activity
    {
        $workflow = $this->completeWorkflow($unit, $owner);
        $activity = $workflow['activity'];
        $snapshot = $activity->plan_snapshot;
        $snapshot['activity_type'] = $type;
        $startsAt = now()->subMonths($monthsAgo)->startOfDay();
        $actual = $participants * 100_000;

        $activity->update([
            'plan_snapshot' => $snapshot,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
            'budget_ceiling' => $actual,
        ]);
        $activity->plan->currentVersionRecord()->update(['activity_type' => $type]);
        $activity->participants()->delete();
        Participant::factory()->count($participants)->create(['activity_id' => $activity->id]);
        $activity->transactions()->firstOrFail()->update(['amount' => $actual]);
        $activity->evaluations()->firstOrFail()->update([
            'score' => $outcome,
            'completion_percent' => $outcome,
            'successful_practices' => 'Checkpoint evidence per sesi.',
            'improvement_areas' => 'Rekonsiliasi harian perlu diperkuat.',
        ]);
        LpjVersion::factory()->create([
            'lpj_id' => $workflow['lpj']->id,
            'created_by' => $owner->id,
            'version' => 1,
            'status' => LpjStatus::Archived,
        ]);
        $workflow['lpj']->update([
            'status' => LpjStatus::Archived,
            'current_version' => 1,
            'submitted_at' => $startsAt->copy()->addDays(3),
            'pb_verified_at' => $startsAt->copy()->addDays(7),
            'archived_at' => $startsAt->copy()->addDays(8),
        ]);

        return $activity->fresh();
    }
}
