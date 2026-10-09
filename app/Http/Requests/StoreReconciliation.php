<?php

namespace App\Http\Requests;

use App\Models\BudgetLine;
use App\Models\FinancialTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReconciliation extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof FinancialTransaction
            && ($this->user()?->can('reconcile', $transaction) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'budget_line_id' => ['required', 'uuid', Rule::exists('budget_lines', 'id')],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('budget_line_id')) {
                return;
            }

            $transaction = $this->route('transaction');
            $budgetLine = BudgetLine::query()->with('budgetVersion.budget')->find($this->input('budget_line_id'));
            if (! $transaction instanceof FinancialTransaction || $budgetLine?->budgetVersion->budget_id !== $transaction->activity->budget_id) {
                $validator->errors()->add('budget_line_id', 'Baris anggaran harus berasal dari RAB kegiatan yang sama.');
            }
        }];
    }
}
