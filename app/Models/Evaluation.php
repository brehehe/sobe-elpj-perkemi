<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['activity_id', 'supersedes_id', 'created_by', 'reviewed_by', 'version', 'status', 'summary', 'outcomes', 'completion_percent', 'satisfaction_score', 'lessons_learned', 'successful_practices', 'improvement_areas', 'score', 'metrics', 'recommendations', 'formula_version', 'review_notes', 'reviewed_at', 'accepted_at'])]
class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => EvaluationStatus::class,
            'completion_percent' => 'decimal:2',
            'satisfaction_score' => 'decimal:2',
            'score' => 'decimal:2',
            'metrics' => 'array',
            'recommendations' => 'array',
            'reviewed_at' => 'datetime',
            'accepted_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function kpiDefinitions(): HasMany
    {
        return $this->hasMany(KpiDefinition::class)->orderBy('position');
    }
}
