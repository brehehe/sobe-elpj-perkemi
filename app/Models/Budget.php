<?php

namespace App\Models;

use App\Enums\BudgetStatus;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['plan_id', 'unit_id', 'owner_id', 'code', 'status', 'current_version', 'currency', 'submitted_at', 'approved_at'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => BudgetStatus::class,
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(BudgetVersion::class)
            ->where('budget_versions.version', function (QueryBuilder $query): void {
                $query->selectRaw('MAX(latest_budget_versions.version)')
                    ->from('budget_versions as latest_budget_versions')
                    ->whereColumn('latest_budget_versions.budget_id', 'budget_versions.budget_id');
            });
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
