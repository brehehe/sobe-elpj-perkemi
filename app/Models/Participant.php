<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'name', 'email', 'phone', 'organization', 'role', 'attendance_status', 'source', 'source_metadata'])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'source_metadata' => 'array',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
