<?php

namespace App\Http\Requests;

use App\Enums\ReconciliationExceptionDecision;
use App\Models\ReconciliationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideReconciliationException extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $exception = $this->route('reconciliationException');

        return $exception instanceof ReconciliationException
            && ($this->user()?->is_active ?? false)
            && ($this->user()?->can('finance.approve-exception') ?? false)
            && ($this->user()?->isGlobalAdministrator() || $this->user()?->unit_id === $exception->reconciliation->transaction->activity->unit_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReconciliationExceptionDecision::class)],
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
