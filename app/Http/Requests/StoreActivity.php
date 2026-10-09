<?php

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivity extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Activity::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'budget_id' => [
                'required',
                'uuid',
                Rule::exists('budgets', 'id')->where(fn ($query) => $query
                    ->when(! $this->user()?->isGlobalAdministrator(), fn ($scoped) => $scoped->where('unit_id', $this->user()?->unit_id))
                    ->where('status', 'APPROVED')),
            ],
            'pic_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', 'unique:activities,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
