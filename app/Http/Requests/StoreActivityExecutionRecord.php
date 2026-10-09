<?php

namespace App\Http\Requests;

use App\Enums\ActivityExecutionType;
use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityExecutionRecord extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity && ($this->user()?->can('update', $activity) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ActivityExecutionType::class)],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'severity' => ['nullable', 'required_if:type,'.ActivityExecutionType::Incident->value, Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'progress_percent' => ['nullable', 'required_if:type,'.ActivityExecutionType::Progress->value, 'integer', 'min:0', 'max:100'],
            'occurred_at' => ['required', 'date'],
        ];
    }
}
