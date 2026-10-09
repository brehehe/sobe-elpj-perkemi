<?php

namespace App\Models;

use Database\Factories\CorrectiveActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['finding_id', 'submitted_by', 'reviewed_by', 'action', 'evidence_path', 'status', 'review_notes', 'submitted_at', 'reviewed_at'])]
class CorrectiveAction extends Model
{
    /** @use HasFactory<CorrectiveActionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
