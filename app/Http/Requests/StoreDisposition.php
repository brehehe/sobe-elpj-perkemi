<?php

namespace App\Http\Requests;

use App\Models\Disposition;
use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisposition extends FormRequest
{
    public function authorize(): bool
    {
        $lpj = $this->route('lpj');

        return $lpj instanceof Lpj
            && ($this->user()?->can('create', [Disposition::class, $lpj]) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'to_user_id' => [
                'nullable',
                'required_without:to_role',
                'uuid',
                Rule::exists('users', 'id')->where('unit_id', $this->user()?->unit_id),
            ],
            'to_role' => ['nullable', 'required_without:to_user_id', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
