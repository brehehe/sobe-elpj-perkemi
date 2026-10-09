<?php

namespace App\Models;

use App\Enums\BudgetStatus;
use Database\Factories\BudgetVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['budget_id', 'created_by', 'reviewed_by', 'version', 'status', 'total', 'change_reason', 'review_notes', 'checksum', 'is_immutable', 'submitted_at', 'reviewed_at'])]
class BudgetVersion extends Model
{
    /** @use HasFactory<BudgetVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => BudgetStatus::class,
            'total' => 'decimal:2',
            'is_immutable' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('line_number');
    }
}
