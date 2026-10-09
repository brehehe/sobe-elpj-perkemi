<?php

namespace App\Models;

use App\Enums\LpjStatus;
use Database\Factories\LpjFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable([
    'activity_id', 'owner_id', 'status', 'current_version', 'total_amount',
    'executive_summary', 'implementation_narrative', 'outcome_narrative',
    'financial_narrative', 'constraints', 'follow_up_plan', 'draft_revision',
    'draft_updated_by', 'draft_updated_at', 'submitted_at', 'approved_at',
    'pb_verified_at', 'archived_at',
])]
class Lpj extends Model
{
    /** @use HasFactory<LpjFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => LpjStatus::class,
            'total_amount' => 'decimal:2',
            'draft_revision' => 'integer',
            'draft_updated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'pb_verified_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator() && ! $user->can('lpjs.pb_verify'), fn ($builder) => $builder->whereHas('activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function draftUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LpjVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(LpjVersion::class)
            ->where('lpj_versions.version', function (QueryBuilder $query): void {
                $query->selectRaw('MAX(latest_lpj_versions.version)')
                    ->from('lpj_versions as latest_lpj_versions')
                    ->whereColumn('latest_lpj_versions.lpj_id', 'lpj_versions.lpj_id');
            });
    }
}
