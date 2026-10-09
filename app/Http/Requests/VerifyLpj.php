<?php

namespace App\Http\Requests;

use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VerifyLpj extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lpj = $this->route('lpj');

        return $lpj instanceof Lpj && ($this->user()?->can('verify', $lpj) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lpj = $this->route('lpj');

        return [
            'stage' => ['required', Rule::in([
                VerificationStage::Administration->value,
                VerificationStage::Finance->value,
                VerificationStage::Substantive->value,
            ])],
            'status' => ['required', Rule::in([
                VerificationStatus::Verified->value,
                VerificationStatus::Finding->value,
                VerificationStatus::Returned->value,
                VerificationStatus::ClarificationRequired->value,
            ])],
            'notes' => ['nullable', 'required_unless:status,'.VerificationStatus::Verified->value, 'string', 'max:3000'],
            'checklist' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'checklist.*' => ['required', 'array:item,result,notes'],
            'checklist.*.item' => ['required', 'string', 'max:500'],
            'checklist.*.result' => ['required', Rule::in(['PASS', 'FAIL', 'NOT_APPLICABLE'])],
            'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
            'evidence_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'evidence_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('evidence', 'id')->where('activity_id', $lpj instanceof Lpj ? $lpj->activity_id : null),
            ],
            'material_exception' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['status', 'checklist'])) {
                    return;
                }

                $decision = VerificationStatus::tryFrom($this->string('status')->toString());
                $checklist = $this->input('checklist', []);
                $hasFailedItem = is_array($checklist)
                    && collect($checklist)->contains(fn ($item): bool => is_array($item) && ($item['result'] ?? null) === 'FAIL');

                if ($decision === VerificationStatus::Verified && $hasFailedItem) {
                    $validator->errors()->add('checklist', 'Keputusan VERIFIED tidak dapat disimpan selama checklist masih memiliki hasil gagal.');
                }

                if (in_array($decision, [VerificationStatus::Finding, VerificationStatus::Returned], true) && ! $hasFailedItem) {
                    $validator->errors()->add('checklist', 'Keputusan FINDING atau RETURNED wajib didukung minimal satu item checklist yang gagal.');
                }
            },
        ];
    }
}
