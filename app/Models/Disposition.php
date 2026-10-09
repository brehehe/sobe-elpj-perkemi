<?php

namespace App\Models;

use App\Enums\DispositionStatus;
use Database\Factories\DispositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lpj_version_id', 'unit_id', 'from_user_id', 'to_user_id', 'reviewed_by', 'to_role', 'subject', 'instructions', 'evidence_ids', 'response_notes', 'review_notes', 'priority', 'status', 'context', 'due_at', 'acknowledged_at', 'completed_at', 'reviewed_at'])]
class Disposition extends Model
{
    /** @use HasFactory<DispositionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => DispositionStatus::class,
            'evidence_ids' => 'array',
            'due_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->where('unit_id', $user->unit_id));
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
