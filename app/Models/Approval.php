<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use Database\Factories\ApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['lpj_version_id', 'decided_by', 'level', 'decision', 'notes', 'authority_policy_version', 'authority_reference', 'authority_snapshot', 'decision_pack', 'decision_pack_checksum', 'lpj_checksum', 'signature_hash', 'decided_at'])]
class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Approval decisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Approval decisions are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'level' => ApprovalLevel::class,
            'decision' => ApprovalDecision::class,
            'authority_snapshot' => 'array',
            'decision_pack' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function verifiedPack(): HasOne
    {
        return $this->hasOne(VerifiedPack::class);
    }
}
