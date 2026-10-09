<?php

namespace App\Http\Requests;

use App\Models\ReconciliationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveReconciliationException extends FormRequest
{
    public function authorize(): bool
    {
        $exception = $this->route('reconciliationException');

        return $exception instanceof ReconciliationException
            && ($this->user()?->is_active ?? false)
            && ($this->user()?->can('finance.resolve-exception') ?? false)
            && ($this->user()?->isGlobalAdministrator() || $this->user()?->unit_id === $exception->reconciliation->transaction->activity->unit_id);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'resolution_type' => ['required', Rule::in(['MAPPED', 'SOURCE_CORRECTED', 'CLARIFIED', 'APPROVED_EXCEPTION'])],
            'resolution' => ['required', 'string', 'max:5000'],
        ];
    }
}
