<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditTrailIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('audit.view') ?? false;
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
            'object' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'uuid', 'exists:units,id'],
            'actor' => ['nullable', 'uuid', 'exists:users,id'],
            'domain' => ['nullable', 'string', 'max:50', 'regex:/\A[a-z][a-z0-9_-]*\z/'],
            'event' => ['nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9._-]*\z/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'result' => ['nullable', Rule::in(['SUCCEEDED', 'FAILED'])],
            'channel' => ['nullable', Rule::in(['WEB', 'API', 'SYSTEM'])],
            'sort' => ['nullable', Rule::in(['latest', 'oldest'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'Pencarian audit maksimal 100 karakter.',
            'object.max' => 'Referensi object maksimal 100 karakter.',
            'unit.uuid' => 'Unit audit tidak valid.',
            'unit.exists' => 'Unit audit tidak ditemukan.',
            'actor.uuid' => 'Actor audit tidak valid.',
            'actor.exists' => 'Actor audit tidak ditemukan.',
            'domain.regex' => 'Domain audit tidak dikenali.',
            'event.regex' => 'Jenis event audit tidak dikenali.',
            'date_from.date_format' => 'Tanggal awal harus menggunakan format YYYY-MM-DD.',
            'date_to.date_format' => 'Tanggal akhir harus menggunakan format YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
            'result.in' => 'Hasil audit tidak dikenali.',
            'channel.in' => 'Channel audit tidak dikenali.',
            'sort.in' => 'Urutan audit tidak dikenali.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['q', 'object', 'unit', 'actor', 'domain', 'event', 'date_from', 'date_to', 'result', 'channel', 'sort'] as $field) {
            $normalized[$field] = $this->string($field)->squish()->toString() ?: null;
        }

        $this->merge($normalized);
    }
}
