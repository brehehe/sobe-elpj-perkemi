<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['unit_id', 'mandate_id', 'plan_id', 'budget_id', 'pic_id', 'code', 'title', 'description', 'location', 'starts_at', 'ends_at', 'status', 'plan_version', 'budget_version', 'budget_ceiling', 'plan_snapshot', 'budget_snapshot'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ActivityStatus::class,
            'budget_ceiling' => 'decimal:2',
            'plan_snapshot' => 'array',
            'budget_snapshot' => 'array',
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->where('unit_id', $user->unit_id));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(Mandate::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(ActivityAgendaItem::class);
    }

    public function executionRecords(): HasMany
    {
        return $this->hasMany(ActivityExecutionRecord::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function lpj(): HasOne
    {
        return $this->hasOne(Lpj::class);
    }

    public function aiReviews(): HasMany
    {
        return $this->hasMany(AiReview::class);
    }
}
