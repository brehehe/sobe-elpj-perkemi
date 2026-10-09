<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\PlanVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan_id', 'created_by', 'reviewed_by', 'version', 'status', 'activity_type', 'objective', 'output', 'outcome', 'target_participants', 'kpis', 'schedule', 'resources', 'risks', 'source_context', 'change_reason', 'review_notes', 'checksum', 'is_immutable', 'submitted_at', 'reviewed_at'])]
class PlanVersion extends Model
{
    /** @use HasFactory<PlanVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'target_participants' => 'integer',
            'kpis' => 'array',
            'schedule' => 'array',
            'resources' => 'array',
            'risks' => 'array',
            'source_context' => 'array',
            'is_immutable' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
