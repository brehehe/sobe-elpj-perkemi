<?php

namespace App\Http\Requests;

use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLpjDraft extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lpj = $this->route('lpj');

        return $lpj instanceof Lpj && ($this->user()?->can('update', $lpj) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'executive_summary' => ['nullable', 'string', 'max:5000'],
            'implementation_narrative' => ['nullable', 'string', 'max:5000'],
            'outcome_narrative' => ['nullable', 'string', 'max:5000'],
            'financial_narrative' => ['nullable', 'string', 'max:5000'],
            'constraints' => ['nullable', 'string', 'max:5000'],
            'follow_up_plan' => ['nullable', 'string', 'max:5000'],
            'draft_revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'draft_revision.required' => 'Versi draft wajib dikirim untuk mencegah perubahan tertimpa.',
            '*.max' => 'Setiap narasi maksimal 5.000 karakter.',
        ];
    }
}
