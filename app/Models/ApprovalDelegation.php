<?php

namespace App\Models;

use App\Enums\ApprovalLevel;
use Database\Factories\ApprovalDelegationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delegate_id', 'granted_by', 'unit_id', 'level', 'max_amount', 'reference', 'notes', 'valid_from', 'valid_until', 'revoked_at'])]
class ApprovalDelegation extends Model
{
    /** @use HasFactory<ApprovalDelegationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'level' => ApprovalLevel::class,
            'max_amount' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
