<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudget extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof Budget
            ? ($this->user()?->can('update', $budget) ?? false)
            : ($this->user()?->can('create', Budget::class) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $budget = $this->route('budget');

        return [
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('plans', 'id')->where(fn ($query) => $query
                    ->when(! $this->user()?->isGlobalAdministrator(), fn ($scoped) => $scoped->where('unit_id', $this->user()?->unit_id))
                    ->whereIn('status', ['SUBMITTED', 'APPROVED'])),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('budgets', 'code')->ignore($budget instanceof Budget ? $budget->id : null)],
            'currency' => ['required', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.account_code' => ['required', 'string', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:50'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'change_reason' => [Rule::requiredIf($budget instanceof Budget && $budget->current_version > 0), 'nullable', 'string', 'max:2000'],
        ];
    }
}
