<?php

namespace App\Http\Requests;

use App\Models\AiReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelAiReview extends FormRequest
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
            && $user->can('ai.request')
            && ($review->requested_by === $user->id || $user->isGlobalAdministrator());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
