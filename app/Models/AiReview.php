<?php

namespace App\Models;

use App\Enums\AiReviewStatus;
use Database\Factories\AiReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['activity_id', 'lpj_version_id', 'requested_by', 'reviewed_by', 'type', 'status', 'model', 'prompt_hash', 'prompt_version', 'input_sources', 'output', 'response_id', 'usage', 'latency_ms', 'retry_count', 'confidence', 'limitations', 'human_review_status', 'review_notes', 'reviewed_output', 'reviewed_at'])]
class AiReview extends Model
{
    /** @use HasFactory<AiReviewFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => AiReviewStatus::class,
            'input_sources' => 'array',
            'output' => 'array',
            'usage' => 'array',
            'latency_ms' => 'integer',
            'retry_count' => 'integer',
            'confidence' => 'decimal:4',
            'reviewed_output' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder
                ->whereHas('activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function lpjVersion(): BelongsTo
    {
        return $this->belongsTo(LpjVersion::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function planningDraft(): HasOne
    {
        return $this->hasOne(Plan::class, 'source_ai_review_id');
    }
}
