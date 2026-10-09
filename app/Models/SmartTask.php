<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\SmartTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'unit_id', 'activity_id', 'subject_type', 'subject_id', 'assignee_id',
    'recipient_permission', 'type', 'title', 'description', 'completion_condition',
    'priority', 'status', 'source', 'deduplication_key', 'due_at', 'escalation_path',
    'escalation_at', 'started_at', 'completed_at', 'metadata',
])]
class SmartTask extends Model
{
    /** @use HasFactory<SmartTaskFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'escalation_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
