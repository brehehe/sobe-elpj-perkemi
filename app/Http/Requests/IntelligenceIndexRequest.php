<?php

namespace App\Http\Requests;

use App\Enums\AiReviewStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntelligenceIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny(['ai.request', 'ai.review']) ?? false;
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
            'type' => ['nullable', Rule::in(['PRECHECK', 'NARRATIVE', 'RISK_REVIEW', 'NEXT_ACTIVITY'])],
            'status' => ['nullable', Rule::enum(AiReviewStatus::class)],
            'review_status' => ['nullable', Rule::in(['PENDING', 'ACCEPTED', 'MODIFIED', 'REJECTED'])],
            'model' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['latest', 'oldest'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'Pencarian intelligence maksimal 100 karakter.',
            'unit.uuid' => 'Unit intelligence tidak valid.',
            'unit.exists' => 'Unit intelligence tidak ditemukan.',
            'type.in' => 'Jenis analisis tidak dikenali.',
            'status.in' => 'Status proses intelligence tidak dikenali.',
            'review_status.in' => 'Status review manusia tidak dikenali.',
            'model.max' => 'Nama model maksimal 100 karakter.',
            'sort.in' => 'Urutan intelligence tidak dikenali.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['q', 'unit', 'type', 'status', 'review_status', 'model', 'sort'] as $field) {
            $normalized[$field] = $this->string($field)->squish()->toString() ?: null;
        }

        $this->merge($normalized);
    }
}
