<?php

namespace App\Http\Requests;

use App\Models\ActivityTaxonomy;
use App\Models\PlanVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityTaxonomyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $taxonomy = $this->route('taxonomy');

        return $taxonomy instanceof ActivityTaxonomy && ($this->user()?->can('update', $taxonomy) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ActivityTaxonomy $taxonomy */
        $taxonomy = $this->route('taxonomy');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique(ActivityTaxonomy::class, 'code')->ignore($taxonomy)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'comparable_factors' => ['required', 'array', 'list', 'min:1', 'max:8'],
            'comparable_factors.*' => ['required', 'string', 'max:100', 'distinct'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var ActivityTaxonomy $taxonomy */
            $taxonomy = $this->route('taxonomy');
            $newCode = $this->validated('code');
            $isUsed = PlanVersion::query()->where('activity_type', $taxonomy->code)->exists();

            if ($isUsed && $newCode !== $taxonomy->code) {
                $validator->errors()->add('code', 'Kode taksonomi yang sudah dipakai planning tidak dapat diubah.');
            }

            if ($isUsed && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Taksonomi yang sudah dipakai planning harus tetap aktif untuk menjaga alur historis.');
            }
        }];
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
