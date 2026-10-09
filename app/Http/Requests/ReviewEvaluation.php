<?php

namespace App\Http\Requests;

use App\Enums\EvaluationReviewDecision;
use App\Models\Evaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEvaluation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evaluation = $this->route('evaluation');

        return $evaluation instanceof Evaluation
            && ($this->user()?->can('review', $evaluation) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(EvaluationReviewDecision::class)],
            'notes' => [
                'nullable',
                Rule::requiredIf(fn () => $this->string('decision')->toString() !== EvaluationReviewDecision::Accepted->value),
                'string',
                'max:5000',
            ],
            'severity' => [
                'nullable',
                Rule::requiredIf(fn () => $this->string('decision')->toString() === EvaluationReviewDecision::Finding->value),
                Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']),
            ],
            'due_at' => [
                'nullable',
                Rule::requiredIf(fn () => $this->string('decision')->toString() === EvaluationReviewDecision::Finding->value),
                'date',
                'after_or_equal:today',
            ],
        ];
    }
}
