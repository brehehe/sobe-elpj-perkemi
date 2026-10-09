<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdministrationUserIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
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
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'unverified'])],
            'unit' => ['nullable', 'uuid', 'exists:units,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'Pencarian pengguna maksimal 100 karakter.',
            'role.exists' => 'Role pengguna tidak ditemukan.',
            'status.in' => 'Status pengguna tidak dikenali.',
            'unit.uuid' => 'Unit pengguna tidak valid.',
            'unit.exists' => 'Unit pengguna tidak ditemukan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->string('q')->squish()->toString() ?: null,
            'role' => $this->string('role')->trim()->toString() ?: null,
            'status' => $this->string('status')->trim()->toString() ?: null,
            'unit' => $this->string('unit')->trim()->toString() ?: null,
        ]);
    }
}
