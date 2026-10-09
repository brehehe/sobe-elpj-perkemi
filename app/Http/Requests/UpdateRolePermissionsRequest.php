<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin.manage-roles') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $permissions = $this->input('permissions');

                if (is_array($permissions) && in_array('admin.manage-roles', $permissions, true)) {
                    $validator->errors()->add('permissions', 'Permission pengelolaan role hanya dimiliki Super Admin.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permissions.required' => 'Pilih minimal satu permission untuk role.',
            'permissions.array' => 'Daftar permission tidak valid.',
            'permissions.min' => 'Pilih minimal satu permission untuk role.',
            'permissions.*.exists' => 'Salah satu permission tidak ditemukan.',
            'permissions.*.distinct' => 'Permission tidak boleh duplikat.',
        ];
    }
}
