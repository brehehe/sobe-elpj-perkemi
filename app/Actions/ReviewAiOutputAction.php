<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Events\AiReviewStatusUpdated;
use App\Models\AiReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewAiOutputAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(AiReview $review, User $reviewer, array $data): AiReview
    {
        $reviewedReview = DB::transaction(function () use ($data, $review, $reviewer): AiReview {
            $lockedReview = AiReview::query()->with('activity')->lockForUpdate()->findOrFail($review->id);
            if ($lockedReview->status !== AiReviewStatus::ReviewRequired || $lockedReview->human_review_status !== 'PENDING') {
                throw ValidationException::withMessages(['decision' => 'Hasil ini tidak tersedia untuk review manusia.']);
            }
            if ($lockedReview->requested_by === $reviewer->id) {
                throw ValidationException::withMessages(['reviewer' => 'Peminta analisis tidak boleh mereview hasil AI yang sama.']);
            }

            $decision = (string) $data['decision'];
            $reviewedOutput = match ($decision) {
                'ACCEPTED' => $lockedReview->output,
                'MODIFIED' => [
                    ...($lockedReview->output ?? []),
                    'summary' => $data['modified_summary'],
                    'recommendations' => collect(preg_split('/\r\n|\r|\n/', (string) $data['modified_recommendations']))
                        ->map(fn (string $recommendation): string => trim($recommendation))
                        ->filter()
                        ->values()
                        ->all(),
                ],
                default => null,
            };
            $runStatus = $decision === 'REJECTED'
                ? AiReviewStatus::Cancelled
                : AiReviewStatus::Succeeded;

            $lockedReview->update([
                'status' => $runStatus,
                'reviewed_by' => $reviewer->id,
                'human_review_status' => $decision,
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_output' => $reviewedOutput,
                'reviewed_at' => now(),
            ]);
            $this->recordAuditEvent->handle($lockedReview->activity, 'ai.recommendation_reviewed', $reviewer, AiReviewStatus::ReviewRequired->value, $runStatus->value, [
                'ai_review_id' => $lockedReview->id,
                'prompt_hash' => $lockedReview->prompt_hash,
                'prompt_version' => $lockedReview->prompt_version,
                'review_notes' => $data['review_notes'] ?? null,
                'review_decision' => $decision,
                'output_modified' => $decision === 'MODIFIED',
            ]);

            return $lockedReview->fresh(['requester', 'reviewer']);
        }, attempts: 3);

        AiReviewStatusUpdated::dispatch(
            $reviewedReview->id,
            $reviewedReview->requested_by,
            $reviewedReview->status->value,
            $reviewedReview->type,
        );

        return $reviewedReview;
    }
}
