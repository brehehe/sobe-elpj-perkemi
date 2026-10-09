<?php

namespace App\Models;

use App\Enums\ActivityAgendaStatus;
use Database\Factories\ActivityAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'completed_by', 'title', 'description', 'location', 'starts_at', 'ends_at', 'status', 'notes', 'completed_at'])]
class ActivityAgendaItem extends Model
{
    /** @use HasFactory<ActivityAgendaItemFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ActivityAgendaStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
