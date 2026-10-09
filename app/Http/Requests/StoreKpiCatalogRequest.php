<?php

namespace App\Http\Requests;

use App\Enums\KpiFormula;
use App\Models\KpiCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiCatalogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', KpiCatalog::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_taxonomy_id' => ['nullable', 'uuid', Rule::exists('activity_taxonomies', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique(KpiCatalog::class, 'code')],
            'name' => ['required', 'string', 'max:150'],
            'definition' => ['required', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:50'],
            'formula' => ['required', Rule::enum(KpiFormula::class)],
            'default_weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activity_taxonomy_id' => $this->string('activity_taxonomy_id')->trim()->toString() ?: null,
            'code' => $this->string('code')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'definition' => $this->string('definition')->squish()->toString(),
            'unit' => $this->string('unit')->squish()->toString(),
            'formula' => $this->string('formula')->trim()->upper()->toString(),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
        ]);
    }
}
