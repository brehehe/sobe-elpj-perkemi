<?php

namespace App\Http\Requests;

use App\Models\Mandate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMandate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $mandate = $this->route('mandate');

        return $mandate instanceof Mandate && ($this->user()?->can('update', $mandate) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Mandate $mandate */
        $mandate = $this->route('mandate');

        return [
            'number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mandates', 'number')
                    ->where('unit_id', $mandate->unit_id)
                    ->ignore($mandate),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'EXPIRED'])],
        ];
    }
}
