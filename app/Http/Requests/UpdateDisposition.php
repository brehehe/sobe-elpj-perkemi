<?php

namespace App\Http\Requests;

use App\Enums\DispositionStatus;
use App\Models\Disposition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDisposition extends FormRequest
{
    public function authorize(): bool
    {
        $disposition = $this->route('disposition');

        return $disposition instanceof Disposition
            && ($this->user()?->can('update', $disposition) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $disposition = $this->route('disposition');
        $activityId = $disposition instanceof Disposition
            ? $disposition->lpjVersion->lpj->activity_id
            : null;

        return [
            'status' => ['required', Rule::in([
                DispositionStatus::Acknowledged->value,
                DispositionStatus::InProgress->value,
                DispositionStatus::EvidenceSubmitted->value,
                DispositionStatus::Closed->value,
                DispositionStatus::Reopened->value,
            ])],
            'evidence_ids' => ['nullable', 'required_if:status,'.DispositionStatus::EvidenceSubmitted->value, 'array', 'list', 'min:1', 'max:100'],
            'evidence_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('evidence', 'id')->where('activity_id', $activityId),
            ],
            'notes' => ['nullable', 'required_if:status,'.DispositionStatus::EvidenceSubmitted->value, 'string', 'max:3000'],
        ];
    }
}
