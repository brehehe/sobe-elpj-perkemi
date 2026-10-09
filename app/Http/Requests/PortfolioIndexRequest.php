<?php

namespace App\Http\Requests;

use App\Enums\LpjStatus;
use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortfolioIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Lpj::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'uuid', 'exists:units,id'],
            'status' => ['nullable', Rule::enum(LpjStatus::class)],
            'sort' => ['nullable', Rule::in(['updated_desc', 'budget_desc', 'actual_desc'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'Pencarian portfolio maksimal 100 karakter.',
            'unit.uuid' => 'Unit portfolio tidak valid.',
            'unit.exists' => 'Unit portfolio tidak ditemukan.',
            'status.enum' => 'Status LPJ tidak dikenali.',
            'sort.in' => 'Urutan portfolio tidak dikenali.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => $this->string('q')->squish()->toString() ?: null]);
    }
}
