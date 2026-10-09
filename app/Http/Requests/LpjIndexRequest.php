<?php

namespace App\Http\Requests;

use App\Enums\LpjStatus;
use App\Models\Lpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LpjIndexRequest extends FormRequest
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
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(LpjStatus::class)],
            'unit' => ['nullable', 'uuid', Rule::exists('units', 'id')],
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'amount_desc', 'amount_asc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->filled('q') ? preg_replace('/\s+/u', ' ', trim((string) $this->input('q'))) : null,
            'status' => $this->filled('status') ? mb_strtoupper((string) $this->input('status')) : null,
            'unit' => $this->filled('unit') ? (string) $this->input('unit') : null,
            'sort' => $this->filled('sort') ? (string) $this->input('sort') : 'latest',
        ]);
    }
}
