<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActionCenterIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('action-center.view') ?? false;
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
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'next_7_days', 'no_due'])],
            'sort' => ['nullable', Rule::in(['priority', 'due_at', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'unit' => ['nullable', 'uuid', Rule::exists(Unit::class, 'id')],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'Pencarian tugas maksimal 100 karakter.',
            'status.enum' => 'Status tugas tidak dikenali.',
            'priority.in' => 'Prioritas tugas tidak dikenali.',
            'due.in' => 'Filter tenggat tidak dikenali.',
            'sort.in' => 'Urutan tugas tidak dikenali.',
            'direction.in' => 'Arah urutan tugas tidak dikenali.',
            'unit.uuid' => 'Unit tugas tidak valid.',
            'unit.exists' => 'Unit tugas tidak ditemukan.',
            'per_page.in' => 'Jumlah baris per halaman tidak didukung.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->string('q')->squish()->toString() ?: null,
        ]);
    }
}
