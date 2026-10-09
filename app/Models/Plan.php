<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['mandate_id', 'unit_id', 'owner_id', 'source_ai_review_id', 'code', 'status', 'current_version', 'submitted_at', 'approved_at'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->where('unit_id', $user->unit_id));
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(Mandate::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sourceAiReview(): BelongsTo
    {
        return $this->belongsTo(AiReview::class, 'source_ai_review_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(PlanVersion::class)
            ->where('plan_versions.version', function (QueryBuilder $query): void {
                $query->selectRaw('MAX(latest_plan_versions.version)')
                    ->from('plan_versions as latest_plan_versions')
                    ->whereColumn('latest_plan_versions.plan_id', 'plan_versions.plan_id');
            });
    }

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
