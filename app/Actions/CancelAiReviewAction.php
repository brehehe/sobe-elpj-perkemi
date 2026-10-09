<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Events\AiReviewStatusUpdated;
use App\Models\AiReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelAiReviewAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(AiReview $review, User $user): AiReview
    {
        $cancelledReview = DB::transaction(function () use ($review, $user): AiReview {
            $lockedReview = AiReview::query()
                ->with('activity')
                ->lockForUpdate()
                ->findOrFail($review->id);

            if (! in_array($lockedReview->status, [AiReviewStatus::Queued, AiReviewStatus::Running], true)) {
                throw ValidationException::withMessages([
                    'review' => 'Hanya pemeriksaan yang masih mengantre atau berjalan yang dapat dibatalkan.',
                ]);
            }

            $fromStatus = $lockedReview->status;
            $lockedReview->update([
                'status' => AiReviewStatus::Cancelled,
                'limitations' => 'Pemeriksaan dibatalkan pengguna sebelum hasil digunakan.',
            ]);
            $this->recordAuditEvent->handle($lockedReview->activity, 'ai.review_cancelled', $user, $fromStatus->value, AiReviewStatus::Cancelled->value, [
                'ai_review_id' => $lockedReview->id,
                'prompt_hash' => $lockedReview->prompt_hash,
                'prompt_version' => $lockedReview->prompt_version,
            ]);

            return $lockedReview;
        }, attempts: 3);

        AiReviewStatusUpdated::dispatch(
            $cancelledReview->id,
            $cancelledReview->requested_by,
            $cancelledReview->status->value,
            $cancelledReview->type,
        );

        return $cancelledReview->fresh(['requester', 'reviewer']);
    }
}
