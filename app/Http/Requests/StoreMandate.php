<?php

namespace App\Http\Requests;

use App\Models\Mandate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMandate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Mandate::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mandates', 'number')->where('unit_id', $this->user()?->unit_id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'status' => ['required', 'in:ACTIVE,INACTIVE,EXPIRED'],
        ];
    }
}
