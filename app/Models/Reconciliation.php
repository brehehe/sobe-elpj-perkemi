<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Database\Factories\ReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['financial_transaction_id', 'budget_line_id', 'reconciled_by', 'status', 'expected_amount', 'actual_amount', 'variance', 'checks', 'notes', 'reconciled_at'])]
class Reconciliation extends Model
{
    /** @use HasFactory<ReconciliationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'variance' => 'decimal:2',
            'checks' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class);
    }
}
