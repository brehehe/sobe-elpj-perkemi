<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Database\Factories\FinancialTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['activity_id', 'budget_line_id', 'recorded_by', 'source', 'external_id', 'reference_number', 'account_code', 'type', 'description', 'counterparty', 'payment_method', 'transaction_date', 'amount', 'currency', 'status', 'reconciliation_status', 'source_payload'])]
class FinancialTransaction extends Model
{
    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'reconciliation_status' => ReconciliationStatus::class,
            'source_payload' => 'array',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder
                ->whereHas('activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class);
    }
}
