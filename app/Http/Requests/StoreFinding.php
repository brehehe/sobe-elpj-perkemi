<?php

namespace App\Http\Requests;

use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinding extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lpj = $this->route('lpj');

        return $lpj instanceof Lpj
            && ($this->user()?->can('findings.manage') ?? false)
            && ($this->user()?->can('view', $lpj) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'severity' => ['required', 'in:LOW,MEDIUM,HIGH,CRITICAL'],
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:5000'],
            'criteria' => ['required', 'string', 'max:3000'],
            'condition' => ['required', 'string', 'max:3000'],
            'cause' => ['required', 'string', 'max:3000'],
            'risk' => ['required', 'string', 'max:3000'],
            'recommendation' => ['required', 'string', 'max:3000'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('unit_id', $this->user()?->unit_id)],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
