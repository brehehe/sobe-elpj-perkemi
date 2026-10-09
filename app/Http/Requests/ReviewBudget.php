<?php

namespace App\Http\Requests;

use App\Enums\BudgetStatus;
use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBudget extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof Budget && ($this->user()?->can('review', $budget) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([BudgetStatus::Approved->value, BudgetStatus::Returned->value, BudgetStatus::Rejected->value])],
            'notes' => ['nullable', 'required_unless:decision,APPROVED', 'string', 'max:3000'],
        ];
    }
}
