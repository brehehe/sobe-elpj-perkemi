<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Jobs\ProcessAiReview;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\User;
use App\Services\AiUsageGuard;
use Illuminate\Support\Facades\DB;

class RequestAiReviewAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private AiUsageGuard $usageGuard,
        private ProcessAiReviewAction $processAiReview,
    ) {}

    public function handle(Activity $activity, User $user, string $type): AiReview
    {
        [$review, $sources] = $this->createQueuedReview($activity, $user, $type);

        ProcessAiReview::dispatch($review->id, $sources)->afterCommit();

        return $review->fresh();
    }

    public function handleSynchronously(Activity $activity, User $user, string $type): AiReview
    {
        [$review, $sources] = $this->createQueuedReview($activity, $user, $type);
        $this->processAiReview->handle($review->id, $sources);

        return $review->fresh();
    }

    /**
     * @return array{AiReview, array<string, mixed>}
     */
    private function createQueuedReview(Activity $activity, User $user, string $type): array
    {
        $activity->load('lpj.currentVersionRecord');
        $activity->loadCount(['participants', 'transactions', 'evidence', 'evaluations']);
        $activity->loadSum('transactions', 'amount');
        $sources = [
            'activity_id' => $activity->id,
            'activity_code' => $activity->code,
            'status' => $activity->status->value,
            'budget_ceiling' => (float) $activity->budget_ceiling,
            'transaction_total' => (float) ($activity->transactions_sum_amount ?? 0),
            'participant_count' => $activity->participants_count,
            'evidence_count' => $activity->evidence_count,
            'evaluation_count' => $activity->evaluations_count,
            'lpj_id' => $activity->lpj?->id,
            'lpj_version' => $activity->lpj?->currentVersionRecord?->version,
            'lpj_checksum' => $activity->lpj?->currentVersionRecord?->checksum,
            'period_start' => $activity->starts_at?->toDateString(),
            'period_end' => $activity->ends_at?->toDateString(),
        ];
        $this->usageGuard->assertCanRun();
        $promptHash = hash('sha256', json_encode([$type, $sources], JSON_THROW_ON_ERROR));
        $sourceManifest = array_filter([
            'activity' => [
                'id' => $activity->id,
                'code' => $activity->code,
                'period_start' => $sources['period_start'],
                'period_end' => $sources['period_end'],
                'source_hash' => hash('sha256', json_encode($sources, JSON_THROW_ON_ERROR)),
            ],
            'lpj' => $activity->lpj ? [
                'id' => $activity->lpj->id,
                'version' => $activity->lpj->currentVersionRecord?->version,
                'checksum' => $activity->lpj->currentVersionRecord?->checksum,
            ] : null,
        ]);
        $review = DB::transaction(function () use ($activity, $promptHash, $sourceManifest, $type, $user): AiReview {
            $review = AiReview::create([
                'activity_id' => $activity->id,
                'lpj_version_id' => $activity->lpj?->currentVersionRecord?->id,
                'requested_by' => $user->id,
                'type' => $type,
                'status' => AiReviewStatus::Queued,
                'prompt_hash' => $promptHash,
                'prompt_version' => (string) config('services.openai.prompt_version'),
                'input_sources' => $sourceManifest,
            ]);
            $this->recordAuditEvent->handle($activity, 'ai.review_queued', $user, null, AiReviewStatus::Queued->value, [
                'ai_review_id' => $review->id,
                'prompt_hash' => $review->prompt_hash,
                'prompt_version' => $review->prompt_version,
                'queue' => 'ai',
            ]);

            return $review;
        });

        return [$review, [
            ...$sources,
            '_safety_identifier' => hash_hmac('sha256', $user->id, (string) config('app.key')),
        ]];
    }
}
