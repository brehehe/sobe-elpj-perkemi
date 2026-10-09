<?php

namespace App\Models;

use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use Database\Factories\VerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lpj_version_id', 'verifier_id', 'stage', 'status', 'notes', 'checklist', 'evidence_ids', 'material_exception', 'verified_at'])]
class Verification extends Model
{
    /** @use HasFactory<VerificationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'stage' => VerificationStage::class,
            'status' => VerificationStatus::class,
            'checklist' => 'array',
            'evidence_ids' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }
}
