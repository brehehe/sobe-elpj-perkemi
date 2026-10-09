<?php

namespace App\Http\Requests;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPlan extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && ($this->user()?->can('review', $plan) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([PlanStatus::Approved->value, PlanStatus::Returned->value, PlanStatus::Rejected->value])],
            'notes' => ['nullable', 'required_unless:decision,APPROVED', 'string', 'max:3000'],
        ];
    }
}
