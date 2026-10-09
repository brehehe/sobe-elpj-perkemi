<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            && $activity->status === ActivityStatus::Completed
            && ($this->user()?->isGlobalAdministrator() === true || (
                ($this->user()?->can('update', $activity) ?? false)
                && $activity->pic_id === $this->user()?->id
            ));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
            'outcomes' => ['required', 'string', 'max:5000'],
            'completion_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'satisfaction_score' => ['required', 'numeric', 'min:0', 'max:5'],
            'lessons_learned' => ['required', 'string', 'max:5000'],
            'successful_practices' => ['required', 'string', 'max:5000'],
            'improvement_areas' => ['required', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'kpis' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'kpis.*' => ['required', 'array:position,actual,evidence_id,notes'],
            'kpis.*.position' => ['required', 'integer', 'min:0', 'distinct'],
            'kpis.*.actual' => ['required', 'numeric', 'min:0'],
            'kpis.*.evidence_id' => [
                'required',
                'uuid',
                Rule::exists('evidence', 'id')->where(fn ($query) => $query
                    ->where('activity_id', $this->route('activity')?->id)
                    ->where('scan_status', EvidenceScanStatus::Registered->value)
                    ->whereNotIn('review_status', [
                        EvidenceReviewStatus::Rejected->value,
                        EvidenceReviewStatus::Superseded->value,
                    ])),
            ],
            'kpis.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
