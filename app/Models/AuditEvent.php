<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable(['unit_id', 'actor_id', 'event_id', 'correlation_id', 'request_id', 'auditable_type', 'auditable_id', 'object_version', 'event_type', 'source_channel', 'authority', 'payload_version', 'from_status', 'to_status', 'result', 'payload', 'evidence_references', 'ip_address', 'user_agent'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'evidence_references' => 'array',
            'payload_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
