<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFinancialTransaction extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'activity_id' => [
                'required',
                'uuid',
                Rule::exists('activities', 'id')->when(
                    ! $this->user()?->isGlobalAdministrator(),
                    fn ($rule) => $rule->where('unit_id', $this->user()?->unit_id),
                ),
            ],
            'external_id' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'account_code' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
