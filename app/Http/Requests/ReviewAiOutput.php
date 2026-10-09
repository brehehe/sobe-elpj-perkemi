<?php

namespace App\Http\Requests;

use App\Models\AiReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAiOutput extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('aiReview');

        return $review instanceof AiReview
            && ($this->user()?->can('ai.review') ?? false)
            && ($this->user()?->isGlobalAdministrator() || $this->user()?->unit_id === $review->activity->unit_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['ACCEPTED', 'MODIFIED', 'REJECTED'])],
            'review_notes' => [
                'nullable',
                'string',
                'max:3000',
                Rule::requiredIf(fn (): bool => in_array($this->input('decision'), ['MODIFIED', 'REJECTED'], true)),
            ],
            'modified_summary' => ['nullable', 'string', 'max:5000', 'required_if:decision,MODIFIED'],
            'modified_recommendations' => ['nullable', 'string', 'max:5000', 'required_if:decision,MODIFIED'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan review manusia wajib dipilih.',
            'decision.in' => 'Keputusan review manusia tidak dikenali.',
            'review_notes.required' => 'Alasan wajib diisi ketika rekomendasi diubah atau ditolak.',
            'review_notes.max' => 'Alasan review maksimal 3.000 karakter.',
            'modified_summary.required_if' => 'Ringkasan hasil modifikasi wajib diisi.',
            'modified_summary.max' => 'Ringkasan hasil modifikasi maksimal 5.000 karakter.',
            'modified_recommendations.required_if' => 'Rekomendasi hasil modifikasi wajib diisi.',
            'modified_recommendations.max' => 'Rekomendasi hasil modifikasi maksimal 5.000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'decision' => $this->string('decision')->trim()->upper()->toString(),
            'review_notes' => $this->string('review_notes')->trim()->toString() ?: null,
            'modified_summary' => $this->string('modified_summary')->trim()->toString() ?: null,
            'modified_recommendations' => $this->string('modified_recommendations')->trim()->toString() ?: null,
        ]);
    }
}
