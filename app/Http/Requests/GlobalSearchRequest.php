<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.required' => 'Masukkan kata kunci pencarian.',
            'q.min' => 'Ketik minimal 2 karakter.',
            'q.max' => 'Kata kunci pencarian maksimal 100 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => $this->string('q')->squish()->toString()]);
    }
}
