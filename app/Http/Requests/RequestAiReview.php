<?php

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestAiReview extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $activity = Activity::find($this->input('activity_id'));

        return $activity !== null
            && ($this->user()?->can('ai.request') ?? false)
            && ($this->user()?->can('view', $activity) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_id' => ['required', 'uuid', 'exists:activities,id'],
            'type' => ['required', 'in:PRECHECK,NARRATIVE,RISK_REVIEW'],
        ];
    }
}
