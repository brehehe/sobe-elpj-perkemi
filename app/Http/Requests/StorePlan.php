<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlan extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            ? ($this->user()?->can('update', $plan) ?? false)
            : ($this->user()?->can('create', Plan::class) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'mandate_id' => [
                'required',
                'uuid',
                Rule::exists('mandates', 'id')->where(fn ($query) => $query
                    ->where('status', 'ACTIVE')
                    ->when(! $this->user()?->isGlobalAdministrator(), fn ($scoped) => $scoped->where('unit_id', $this->user()?->unit_id))),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('plans', 'code')->ignore($plan instanceof Plan ? $plan->id : null)],
            'activity_type' => ['required', 'string', Rule::exists('activity_taxonomies', 'code')->where('is_active', true)],
            'objective' => ['required', 'string', 'max:5000'],
            'output' => ['required', 'string', 'max:5000'],
            'outcome' => ['required', 'string', 'max:5000'],
            'target_participants' => ['required', 'integer', 'min:1', 'max:1000000'],
            'kpis' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'kpis.*' => ['required', 'array:name,definition,baseline,target,unit,formula,weight,period_start,period_end'],
            'kpis.*.name' => ['required', 'string', 'max:150'],
            'kpis.*.definition' => ['nullable', 'string', 'max:2000'],
            'kpis.*.baseline' => ['nullable', 'numeric', 'min:0'],
            'kpis.*.target' => ['required', 'numeric', 'min:0'],
            'kpis.*.unit' => ['required', 'string', 'max:50'],
            'kpis.*.formula' => ['nullable', Rule::in(['HIGHER_IS_BETTER', 'LOWER_IS_BETTER'])],
            'kpis.*.weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'kpis.*.period_start' => ['nullable', 'date'],
            'kpis.*.period_end' => ['nullable', 'date', 'after_or_equal:kpis.*.period_start'],
            'schedule' => ['nullable', 'array:starts_at,ends_at'],
            'schedule.starts_at' => ['nullable', 'date'],
            'schedule.ends_at' => ['nullable', 'date', 'after_or_equal:schedule.starts_at'],
            'resources' => ['nullable', 'array', 'list', 'max:20'],
            'resources.*' => ['string', 'max:500'],
            'risks' => ['nullable', 'array', 'list', 'max:20'],
            'risks.*' => ['required', 'array:risk,mitigation'],
            'risks.*.risk' => ['required_with:risks', 'string', 'max:1000'],
            'risks.*.mitigation' => ['required_with:risks', 'string', 'max:1000'],
            'change_reason' => [Rule::requiredIf($plan instanceof Plan && $plan->current_version > 0), 'nullable', 'string', 'max:2000'],
        ];
    }
}
