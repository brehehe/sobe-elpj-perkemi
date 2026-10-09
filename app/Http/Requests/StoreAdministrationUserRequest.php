<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdministrationUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'unit_id' => [
                'required',
                'uuid',
                Rule::exists('units', 'id'),
                Rule::when(! $actor?->isGlobalAdministrator(), Rule::in([$actor?->unit_id])),
            ],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                Rule::when(! $actor?->isGlobalAdministrator(), Rule::notIn(['Super Admin'])),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'name.max' => 'Nama pengguna maksimal 120 karakter.',
            'email.required' => 'Email organisasi wajib diisi.',
            'email.email' => 'Format email organisasi tidak valid.',
            'email.unique' => 'Email organisasi sudah digunakan.',
            'unit_id.required' => 'Unit organisasi wajib dipilih.',
            'unit_id.exists' => 'Unit organisasi tidak ditemukan.',
            'unit_id.in' => 'Administrator unit hanya dapat mengelola unitnya sendiri.',
            'role.required' => 'Role pengguna wajib dipilih.',
            'role.exists' => 'Role pengguna tidak ditemukan.',
            'role.not_in' => 'Role Super Admin hanya dapat diberikan oleh Super Admin.',
            'is_active.boolean' => 'Status aktif pengguna tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->squish()->toString(),
            'email' => $this->string('email')->lower()->trim()->toString(),
            'unit_id' => $this->string('unit_id')->trim()->toString(),
            'role' => $this->string('role')->trim()->toString(),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
        ]);
    }
}
