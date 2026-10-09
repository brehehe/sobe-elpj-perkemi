<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Evidence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreEvidence extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            && ($this->user()?->can('create', Evidence::class) ?? false)
            && ($this->user()?->can('update', $activity) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activity = $this->route('activity');

        return [
            'type' => ['required', 'in:LETTER,ATTENDANCE,PHOTO,INVOICE,RECEIPT,REPORT,OTHER'],
            'title' => ['required', 'string', 'max:255'],
            'file' => [
                'required',
                File::types(array_keys(config('evidence.allowed_extensions')))
                    ->max(config('evidence.max_size_kb').'kb'),
                'extensions:'.implode(',', array_keys(config('evidence.allowed_extensions'))),
            ],
            'captured_at' => ['nullable', 'date'],
            'supersedes_id' => ['nullable', 'uuid', Rule::exists('evidence', 'id')->where('activity_id', $activity instanceof Activity ? $activity->id : null)],
            'access_classification' => ['sometimes', Rule::in(['PUBLIC', 'INTERNAL', 'RESTRICTED'])],
        ];
    }
}
