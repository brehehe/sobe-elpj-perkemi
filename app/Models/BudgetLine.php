<?php

namespace App\Models;

use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['budget_version_id', 'line_number', 'account_code', 'description', 'quantity', 'unit', 'unit_cost', 'subtotal'])]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }
}
