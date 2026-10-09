<?php

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityIntelligenceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Activity::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::exists('activity_taxonomies', 'code')],
            'unit' => ['nullable', 'uuid', 'exists:units,id'],
            'period' => ['nullable', Rule::in(['12', '24', '36', 'all'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.exists' => 'Jenis kegiatan tidak dikenali.',
            'unit.uuid' => 'Unit analisis tidak valid.',
            'unit.exists' => 'Unit analisis tidak ditemukan.',
            'period.in' => 'Periode analisis tidak dikenali.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->string('type')->trim()->upper()->toString();

        $this->merge(['type' => $type !== '' ? $type : null]);
    }
}
