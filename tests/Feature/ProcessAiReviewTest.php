<?php

namespace Tests\Feature;

use App\Actions\ProcessAiReviewAction;
use App\Actions\RequestAiReviewAction;
use App\Enums\AiReviewStatus;
use App\Events\AiReviewStatusUpdated;
use App\Jobs\ProcessAiReview;
use App\Models\AiReview;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ProcessAiReviewTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        config()->set('services.openai.key');
    }

    public function test_request_is_persisted_as_queued_and_dispatched_to_the_ai_queue(): void
    {
        Queue::fake([ProcessAiReview::class]);
        $workflow = $this->completeWorkflow();

        $this->actingAs($workflow['owner'])
            ->post('/ai-reviews', [
                'activity_id' => $workflow['activity']->id,
                'type' => 'PRECHECK',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $review = AiReview::query()->sole();

        $this->assertSame(AiReviewStatus::Queued, $review->status);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $workflow['activity']->id,
            'event_type' => 'ai.review_queued',
            'to_status' => AiReviewStatus::Queued->value,
        ]);
        Queue::assertPushed(ProcessAiReview::class, fn (ProcessAiReview $job): bool => $job->reviewId === $review->id
            && $job->queue === 'ai');
    }

    public function test_queued_job_completes_the_review_and_broadcasts_its_status(): void
    {
        Event::fake([AiReviewStatusUpdated::class]);
        [$job, $review] = $this->queuedReview();

        $job->handle(app(ProcessAiReviewAction::class));

        $review->refresh();
        $this->assertSame(AiReviewStatus::ReviewRequired, $review->status);
        $this->assertSame('local-rules-engine', $review->model);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $review->activity_id,
            'event_type' => 'ai.review_completed',
        ]);
        Event::assertDispatched(AiReviewStatusUpdated::class, fn (AiReviewStatusUpdated $event): bool => $event->reviewId === $review->id
            && $event->requestedBy === $review->requested_by
            && $event->status === AiReviewStatus::ReviewRequired->value);
    }

    public function test_permanent_provider_error_marks_the_review_failed_without_blocking_workflow(): void
    {
        $this->enableExternalProvider();
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'invalid request']], 400)]);
        Event::fake([AiReviewStatusUpdated::class]);
        [$job, $review] = $this->queuedReview();
        $job->withFakeQueueInteractions();

        $job->handle(app(ProcessAiReviewAction::class));

        $job->assertFailedWith(RequestException::class);
        $review->refresh();
        $this->assertSame(AiReviewStatus::Failed, $review->status);
        $this->assertSame('Pemeriksaan AI gagal; workflow tetap dapat dilanjutkan tanpa AI.', $review->limitations);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $review->activity_id,
            'event_type' => 'ai.review_failed',
            'result' => 'FAILED',
        ]);
        Event::assertDispatched(AiReviewStatusUpdated::class, fn (AiReviewStatusUpdated $event): bool => $event->reviewId === $review->id
            && $event->status === AiReviewStatus::Failed->value);
    }

    public function test_rate_limit_error_is_rethrown_for_queue_retry_and_keeps_review_running(): void
    {
        $this->enableExternalProvider();
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'rate limited']], 429)]);
        Event::fake([AiReviewStatusUpdated::class]);
        [$job, $review] = $this->queuedReview();

        try {
            $job->handle(app(ProcessAiReviewAction::class));
            $this->fail('A rate limit response should be retried by the queue.');
        } catch (RequestException $exception) {
            $this->assertSame(429, $exception->response->status());
        }

        $this->assertSame(AiReviewStatus::Running, $review->fresh()->status);
        $this->assertDatabaseMissing('audit_events', [
            'auditable_id' => $review->activity_id,
            'event_type' => 'ai.review_failed',
        ]);
        Event::assertDispatchedTimes(AiReviewStatusUpdated::class, 1);
        Event::assertDispatched(AiReviewStatusUpdated::class, fn (AiReviewStatusUpdated $event): bool => $event->status === AiReviewStatus::Running->value);
    }

    public function test_retry_after_header_releases_the_job_with_a_bounded_provider_delay(): void
    {
        $this->enableExternalProvider();
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(
            ['error' => ['message' => 'rate limited']],
            429,
            ['Retry-After' => '42'],
        )]);
        [$job, $review] = $this->queuedReview();
        $job->withFakeQueueInteractions();

        $job->handle(app(ProcessAiReviewAction::class));

        $job->assertReleased(42);
        $this->assertSame(AiReviewStatus::Running, $review->fresh()->status);
    }

    public function test_requester_can_cancel_a_queued_review_and_processing_becomes_a_no_op(): void
    {
        Queue::fake([ProcessAiReview::class]);
        Event::fake([AiReviewStatusUpdated::class]);
        $workflow = $this->completeWorkflow();
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'PRECHECK');

        $this->actingAs($workflow['owner'])
            ->patch('/ai-reviews/'.$review->id.'/cancel')
            ->assertRedirect()
            ->assertSessionHas('success');
        app(ProcessAiReviewAction::class)->handle($review->id, []);

        $review->refresh();
        $this->assertSame(AiReviewStatus::Cancelled, $review->status);
        $this->assertNull($review->model);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $review->activity_id,
            'event_type' => 'ai.review_cancelled',
            'from_status' => AiReviewStatus::Queued->value,
            'to_status' => AiReviewStatus::Cancelled->value,
        ]);
        Event::assertDispatched(AiReviewStatusUpdated::class, fn (AiReviewStatusUpdated $event): bool => $event->reviewId === $review->id
            && $event->status === AiReviewStatus::Cancelled->value);
    }

    public function test_another_requester_cannot_cancel_someone_elses_ai_review(): void
    {
        Queue::fake([ProcessAiReview::class]);
        $workflow = $this->completeWorkflow();
        $otherRequester = $this->userWithRole('Owner/PIC', $workflow['unit']);
        $review = app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'PRECHECK');

        $this->actingAs($otherRequester)
            ->patch('/ai-reviews/'.$review->id.'/cancel')
            ->assertForbidden();

        $this->assertSame(AiReviewStatus::Queued, $review->fresh()->status);
    }

    public function test_ai_job_limits_concurrency_by_requester_and_unit(): void
    {
        [$job, $review] = $this->queuedReview();

        $middleware = $job->middleware();

        $this->assertCount(2, $middleware);
        $this->assertSame('ai-user-'.$review->requested_by, $middleware[0]->key);
        $this->assertSame('ai-unit-'.$review->activity->unit_id, $middleware[1]->key);
    }

    public function test_private_ai_review_channel_only_authorizes_its_uuid_owner(): void
    {
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app');
        require base_path('routes/channels.php');
        $workflow = $this->completeWorkflow();
        $otherUser = $this->userWithRole('Owner/PIC', $workflow['unit']);
        $payload = [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$workflow['owner']->id}.ai-reviews",
        ];

        $this->actingAs($workflow['owner'])
            ->post('/broadcasting/auth', $payload)
            ->assertOk();

        $this->actingAs($otherUser)
            ->post('/broadcasting/auth', $payload)
            ->assertForbidden();
    }

    /** @return array{ProcessAiReview, AiReview} */
    private function queuedReview(): array
    {
        Queue::fake([ProcessAiReview::class]);
        $workflow = $this->completeWorkflow();
        app(RequestAiReviewAction::class)->handle($workflow['activity'], $workflow['owner'], 'RISK_REVIEW');
        $review = AiReview::query()->sole();
        $job = null;
        Queue::assertPushed(ProcessAiReview::class, function (ProcessAiReview $queuedJob) use (&$job): bool {
            $job = $queuedJob;

            return true;
        });

        $this->assertInstanceOf(ProcessAiReview::class, $job);

        return [$job, $review];
    }

    private function enableExternalProvider(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'test-model');
        config()->set('services.openai.max_retries', 0);
    }
}
