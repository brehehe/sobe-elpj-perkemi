<?php

namespace App\Models;

use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['activity_id', 'supersedes_id', 'uploaded_by', 'reviewed_by', 'type', 'version', 'title', 'disk', 'path', 'original_name', 'mime_type', 'detected_mime_type', 'size', 'sha256', 'scan_status', 'scan_engine', 'scan_notes', 'scanned_at', 'linked_at', 'review_status', 'review_notes', 'reviewed_at', 'access_classification', 'captured_at', 'metadata'])]
class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'scan_status' => EvidenceScanStatus::class,
            'scanned_at' => 'datetime',
            'linked_at' => 'datetime',
            'review_status' => EvidenceReviewStatus::class,
            'reviewed_at' => 'datetime',
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->whereHas('activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function kpiResults(): HasMany
    {
        return $this->hasMany(KpiResult::class);
    }

    public function lifecycleStatus(): string
    {
        if ($this->scan_status !== EvidenceScanStatus::Registered) {
            return $this->scan_status->value;
        }

        return match ($this->review_status) {
            EvidenceReviewStatus::Pending => 'LINKED',
            default => $this->review_status->value,
        };
    }

    public function isDownloadable(): bool
    {
        return $this->scan_status === EvidenceScanStatus::Registered;
    }
}
