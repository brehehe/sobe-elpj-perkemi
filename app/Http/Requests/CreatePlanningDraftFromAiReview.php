<?php

namespace App\Http\Requests;

use App\Enums\AiReviewStatus;
use App\Models\AiReview;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class CreatePlanningDraftFromAiReview extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('aiReview');
        $user = $this->user();

        return $review instanceof AiReview
            && $user !== null
            && $user->can('create', Plan::class)
            && ($user->isGlobalAdministrator() || $user->unit_id === $review->activity->unit_id)
            && $review->status === AiReviewStatus::Succeeded
            && in_array($review->human_review_status, ['ACCEPTED', 'MODIFIED'], true);
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
