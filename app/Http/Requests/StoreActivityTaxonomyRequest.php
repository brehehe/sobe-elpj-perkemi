<?php

namespace App\Http\Requests;

use App\Models\ActivityTaxonomy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityTaxonomyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ActivityTaxonomy::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique(ActivityTaxonomy::class, 'code')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'comparable_factors' => ['required', 'array', 'list', 'min:1', 'max:8'],
            'comparable_factors.*' => ['required', 'string', 'max:100', 'distinct'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode taksonomi hanya boleh berisi huruf kapital, angka, tanda hubung, dan garis bawah.',
            'code.unique' => 'Kode taksonomi sudah digunakan.',
            'comparable_factors.min' => 'Minimal satu faktor pembanding wajib ditetapkan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->string('code')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'description' => $this->string('description')->squish()->toString() ?: null,
            'comparable_factors' => collect($this->input('comparable_factors', []))->map(fn (mixed $factor): string => str($factor)->squish()->toString())->filter()->values()->all(),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
        ]);
    }
}
