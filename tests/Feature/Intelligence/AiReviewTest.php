<?php

namespace Tests\Feature\Intelligence;

use App\Actions\RequestAiReviewAction;
use App\Enums\AiReviewStatus;
use App\Models\AiReview;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AiReviewTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        config()->set('services.openai.key');
    }

    public function test_application_uses_audited_local_fallback_without_openai_key(): void
    {
        $workflow = $this->completeWorkflow();
        Sanctum::actingAs($workflow['owner']);
        $initialStatus = $workflow['lpj']->status;

        $this->postJson('/api/v1/ai-reviews', ['activity_id' => $workflow['activity']->id, 'type' => 'PRECHECK'])
            ->assertAccepted()
            ->assertJsonPath('data.status', AiReviewStatus::ReviewRequired->value)
            ->assertJsonPath('data.model', 'local-rules-engine');

        $review = AiReview::query()->firstOrFail();
        $this->assertSame('PENDING', $review->human_review_status);
        $this->assertSame($initialStatus, $workflow['lpj']->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'ai.review_completed', 'auditable_id' => $workflow['activity']->id]);
    }

    public function test_openai_adapter_requests_strict_structured_output_without_storing_request(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'test-model');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_123',
                'status' => 'completed',
                'usage' => ['input_tokens' => 120, 'output_tokens' => 42],
                'output' => [['content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'Data konsisten.', 'risks' => [], 'recommendations' => ['Tinjau manusia.'],
                        'confidence' => 0.82, 'limitations' => 'Terbatas pada sumber yang diberikan.',
                    ], JSON_THROW_ON_ERROR),
                ]]]],
            ]),
        ]);
        $workflow = $this->completeWorkflow();

        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');

        $this->assertSame(AiReviewStatus::ReviewRequired, $review->status);
        $this->assertSame('test-model', $review->model);
        $this->assertSame('Data konsisten.', $review->output['summary']);
        $this->assertSame('resp_test_123', $review->response_id);
        $this->assertSame(['input_tokens' => 120, 'output_tokens' => 42], $review->usage);
        $this->assertSame(0, $review->retry_count);
        $this->assertSame('lpj-intelligence-v1', $review->prompt_version);
        $this->assertArrayHasKey('activity', $review->input_sources);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $review->id)
            && $request['store'] === false
            && $request['reasoning']['effort'] === 'high'
            && $request['max_output_tokens'] === 20_000
            && preg_match('/^[a-f0-9]{64}$/', (string) $request['safety_identifier']) === 1
            && ! str_contains((string) $request['input'][1]['content'], $workflow['owner']->id)
            && $request['text']['format']['strict'] === true);
    }

    public function test_ai_output_requires_a_separate_human_reviewer(): void
    {
        $workflow = $this->completeWorkflow();
        $reviewer = $this->userWithRole('Verifier Substantive', $workflow['unit']);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'PRECHECK');

        $this->actingAs($workflow['owner'])->patch("/ai-reviews/{$review->id}/human-review", ['decision' => 'ACCEPTED'])->assertForbidden();
        $this->actingAs($reviewer)->patch("/ai-reviews/{$review->id}/human-review", ['decision' => 'ACCEPTED'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('ACCEPTED', $review->fresh()->human_review_status);
        $this->assertSame(AiReviewStatus::Succeeded, $review->fresh()->status);
        $this->assertSame($reviewer->id, $review->fresh()->reviewed_by);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'ai.recommendation_reviewed', 'auditable_id' => $workflow['activity']->id]);
    }

    public function test_external_ai_daily_token_guard_blocks_a_new_run_after_the_budget_is_consumed(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.daily_budget', 100);
        Http::preventStrayRequests();
        $workflow = $this->completeWorkflow();
        AiReview::factory()->create([
            'activity_id' => $workflow['activity']->id,
            'requested_by' => $workflow['owner']->id,
            'status' => AiReviewStatus::Succeeded,
            'model' => 'test-model',
            'usage' => ['total_tokens' => 100],
        ]);

        try {
            app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');
            $this->fail('Usage guard should reject a run after the daily token budget is consumed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Batas token OpenAI harian telah tercapai. Workflow inti tetap tersedia; coba lagi setelah anggaran harian diperbarui.',
                $exception->errors()['type'][0],
            );
        }

        $this->assertSame(1, AiReview::query()->count());
        Http::assertNothingSent();
    }

    public function test_external_ai_monthly_token_guard_blocks_a_new_run_after_the_budget_is_consumed(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.daily_budget', 0);
        config()->set('services.openai.monthly_budget', 100);
        Http::preventStrayRequests();
        $workflow = $this->completeWorkflow();
        AiReview::factory()->create([
            'activity_id' => $workflow['activity']->id,
            'requested_by' => $workflow['owner']->id,
            'status' => AiReviewStatus::Succeeded,
            'model' => 'test-model',
            'usage' => ['total_tokens' => 100],
        ]);

        try {
            app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');
            $this->fail('Usage guard should reject a run after the monthly token budget is consumed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Batas token OpenAI bulanan telah tercapai. Workflow inti tetap tersedia; coba lagi setelah anggaran bulanan diperbarui.',
                $exception->errors()['type'][0],
            );
        }

        $this->assertSame(1, AiReview::query()->count());
        Http::assertNothingSent();
    }

    public function test_ai_rate_limiter_applies_user_unit_and_organization_budgets(): void
    {
        $workflow = $this->completeWorkflow();
        $request = HttpRequest::create('/ai-reviews', 'POST');
        $request->setUserResolver(fn () => $workflow['owner']);

        $limits = RateLimiter::limiter('ai')($request);

        $this->assertCount(3, $limits);
        $this->assertSame([10, 30, 120], collect($limits)->pluck('maxAttempts')->all());
        $this->assertSame('ai:user:'.$workflow['owner']->id, $limits[0]->key);
        $this->assertSame('ai:unit:'.$workflow['unit']->id, $limits[1]->key);
        $this->assertSame('ai:organization', $limits[2]->key);
    }
}
