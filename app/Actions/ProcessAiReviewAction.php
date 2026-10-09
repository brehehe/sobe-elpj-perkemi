<?php

namespace App\Actions;

use App\Contracts\AiReviewer;
use App\Enums\AiReviewStatus;
use App\Events\AiReviewStatusUpdated;
use App\Models\AiReview;
use App\Services\AiUsageGuard;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessAiReviewAction
{
    public function __construct(
        private AiReviewer $reviewer,
        private RecordAuditEventAction $recordAuditEvent,
        private AiUsageGuard $usageGuard,
    ) {}

    /** @param array<string, mixed> $sources */
    public function handle(string $reviewId, array $sources): void
    {
        $startedReview = DB::transaction(function () use ($reviewId): ?array {
            $review = AiReview::query()
                ->with(['activity', 'requester'])
                ->lockForUpdate()
                ->find($reviewId);

            if ($review === null || ! in_array($review->status, [AiReviewStatus::Queued, AiReviewStatus::Running], true)) {
                return null;
            }

            if ($review->status === AiReviewStatus::Running) {
                return [$review, false];
            }

            $review->update(['status' => AiReviewStatus::Running]);
            $this->recordAuditEvent->handle($review->activity, 'ai.review_started', $review->requester, AiReviewStatus::Queued->value, AiReviewStatus::Running->value, [
                'ai_review_id' => $review->id,
                'prompt_hash' => $review->prompt_hash,
                'prompt_version' => $review->prompt_version,
                'queue' => 'ai',
            ]);

            return [$review, true];
        }, attempts: 3);

        if ($startedReview === null) {
            return;
        }

        [$review, $startedNow] = $startedReview;

        if ($startedNow) {
            AiReviewStatusUpdated::dispatch(
                $review->id,
                $review->requested_by,
                AiReviewStatus::Running->value,
                $review->type,
            );
        }

        $this->usageGuard->assertCanRun();
        $startedAt = hrtime(true);
        $result = $this->reviewer->review($sources, $review->type, $review->id);
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $completedReview = DB::transaction(function () use ($latencyMs, $result, $review): ?AiReview {
            $lockedReview = AiReview::query()
                ->with(['activity', 'requester'])
                ->lockForUpdate()
                ->find($review->id);

            if ($lockedReview === null || $lockedReview->status !== AiReviewStatus::Running) {
                return null;
            }

            $lockedReview->update([
                'status' => AiReviewStatus::ReviewRequired,
                'model' => $result['model'],
                'output' => array_replace($lockedReview->output ?? [], $result['output']),
                'response_id' => $result['response_id'],
                'usage' => $result['usage'],
                'latency_ms' => $latencyMs,
                'retry_count' => $result['retry_count'],
                'confidence' => $result['confidence'],
                'limitations' => $result['limitations'],
            ]);
            $this->recordAuditEvent->handle($lockedReview->activity, 'ai.review_completed', $lockedReview->requester, AiReviewStatus::Running->value, AiReviewStatus::ReviewRequired->value, [
                'ai_review_id' => $lockedReview->id,
                'model' => $result['model'],
                'prompt_hash' => $lockedReview->prompt_hash,
                'prompt_version' => $lockedReview->prompt_version,
                'response_id' => $result['response_id'],
                'source_ids' => collect($lockedReview->input_sources)->pluck('id')->filter()->values()->all(),
            ]);

            return $lockedReview;
        });

        if ($completedReview !== null) {
            AiReviewStatusUpdated::dispatch(
                $completedReview->id,
                $completedReview->requested_by,
                $completedReview->status->value,
                $completedReview->type,
            );
        }
    }

    public function markFailed(string $reviewId, Throwable $exception, int $retryCount): void
    {
        $failedReview = DB::transaction(function () use ($exception, $retryCount, $reviewId): ?AiReview {
            $review = AiReview::query()
                ->with(['activity', 'requester'])
                ->lockForUpdate()
                ->find($reviewId);

            if ($review === null || ! in_array($review->status, [AiReviewStatus::Queued, AiReviewStatus::Running], true)) {
                return null;
            }

            $fromStatus = $review->status;
            $review->update([
                'status' => AiReviewStatus::Failed,
                'retry_count' => max(0, $retryCount),
                'limitations' => 'Pemeriksaan AI gagal; workflow tetap dapat dilanjutkan tanpa AI.',
            ]);
            $this->recordAuditEvent->handle($review->activity, 'ai.review_failed', $review->requester, $fromStatus->value, AiReviewStatus::Failed->value, [
                'ai_review_id' => $review->id,
                'prompt_hash' => $review->prompt_hash,
                'prompt_version' => $review->prompt_version,
                'error_type' => class_basename($exception),
            ], result: 'FAILED');

            return $review;
        });

        if ($failedReview !== null) {
            AiReviewStatusUpdated::dispatch(
                $failedReview->id,
                $failedReview->requested_by,
                $failedReview->status->value,
                $failedReview->type,
            );
        }
    }
}
