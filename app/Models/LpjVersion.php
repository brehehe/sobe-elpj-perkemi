<?php

namespace App\Models;

use App\Enums\LpjStatus;
use Database\Factories\LpjVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['lpj_id', 'created_by', 'submitted_by', 'version', 'status', 'snapshot', 'checksum', 'is_immutable', 'submitted_at'])]
class LpjVersion extends Model
{
    /** @use HasFactory<LpjVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['status' => LpjStatus::class, 'snapshot' => 'array', 'is_immutable' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function lpj(): BelongsTo
    {
        return $this->belongsTo(Lpj::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(Disposition::class);
    }

    public function aiReviews(): HasMany
    {
        return $this->hasMany(AiReview::class);
    }

    public function archive(): HasOne
    {
        return $this->hasOne(Archive::class);
    }

    public function verifiedPack(): HasOne
    {
        return $this->hasOne(VerifiedPack::class);
    }
}
