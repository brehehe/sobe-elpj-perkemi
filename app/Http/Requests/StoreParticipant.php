<?php

namespace App\Http\Requests;

use App\Enums\ParticipantRole;
use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParticipant extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['sometimes', Rule::enum(ParticipantRole::class)],
            'attendance_status' => ['required', 'in:REGISTERED,ATTENDED,ABSENT'],
        ];
    }
}
