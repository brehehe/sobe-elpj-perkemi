<?php

namespace App\Models;

use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use Database\Factories\ActivityExecutionRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'created_by', 'resolved_by', 'type', 'title', 'details', 'severity', 'progress_percent', 'status', 'occurred_at', 'resolution_notes', 'resolved_at'])]
class ActivityExecutionRecord extends Model
{
    /** @use HasFactory<ActivityExecutionRecordFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => ActivityExecutionType::class,
            'status' => ActivityExecutionStatus::class,
            'progress_percent' => 'integer',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
