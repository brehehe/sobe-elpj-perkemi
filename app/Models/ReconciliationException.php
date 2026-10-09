<?php

namespace App\Models;

use App\Enums\ReconciliationExceptionDecision;
use App\Enums\ReconciliationExceptionStatus;
use Database\Factories\ReconciliationExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reconciliation_id', 'owner_id', 'resolved_by', 'requested_by', 'decided_by', 'category', 'severity', 'status', 'detail', 'resolution_type', 'resolution', 'is_approved_exception', 'decision', 'authority_policy_version', 'decision_notes', 'due_at', 'requested_at', 'decided_at', 'resolved_at'])]
class ReconciliationException extends Model
{
    /** @use HasFactory<ReconciliationExceptionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ReconciliationExceptionStatus::class,
            'decision' => ReconciliationExceptionDecision::class,
            'is_approved_exception' => 'boolean',
            'due_at' => 'datetime',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder
                ->whereHas('reconciliation.transaction.activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
