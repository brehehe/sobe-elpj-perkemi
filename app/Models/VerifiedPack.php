<?php

namespace App\Models;

use Database\Factories\VerifiedPackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['id', 'lpj_version_id', 'approval_id', 'issued_by', 'verification_number', 'policy_version', 'schema_version', 'disk', 'path', 'checksum', 'lpj_checksum', 'approval_signature_hash', 'public_token', 'metadata', 'issued_at'])]
class VerifiedPack extends Model
{
    /** @use HasFactory<VerifiedPackFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Verified packs are immutable.'));
        static::deleting(fn () => throw new LogicException('Verified packs are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'metadata' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
