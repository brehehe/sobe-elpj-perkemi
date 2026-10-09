<?php

namespace App\Http\Requests;

use App\Enums\EvidenceReviewStatus;
use App\Models\Evidence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEvidence extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evidence = $this->route('evidence');

        return $evidence instanceof Evidence
            && ($this->user()?->can('review', $evidence) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                EvidenceReviewStatus::Verified->value,
                EvidenceReviewStatus::Rejected->value,
            ])],
            'notes' => ['nullable', 'required_if:status,'.EvidenceReviewStatus::Rejected->value, 'string', 'max:3000'],
        ];
    }
}
