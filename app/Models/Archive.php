<?php

namespace App\Models;

use Database\Factories\ArchiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['lpj_version_id', 'verified_pack_id', 'archived_by', 'archive_number', 'disk', 'path', 'checksum', 'verified_pack_checksum', 'public_token', 'retention_policy_version', 'package_schema_version', 'archived_at', 'integrity_verified_at', 'retention_until'])]
class Archive extends Model
{
    /** @use HasFactory<ArchiveFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Archives are immutable.'));
        static::deleting(fn () => throw new LogicException('Archives are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'package_schema_version' => 'integer',
            'archived_at' => 'datetime',
            'integrity_verified_at' => 'datetime',
            'retention_until' => 'datetime',
        ];
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function verifiedPack(): BelongsTo
    {
        return $this->belongsTo(VerifiedPack::class);
    }
}
