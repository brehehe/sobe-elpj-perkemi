<?php

namespace App\Models;

use Database\Factories\MandateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['unit_id', 'created_by', 'number', 'title', 'description', 'issued_at', 'expires_at', 'status', 'source_metadata'])]
class Mandate extends Model
{
    /** @use HasFactory<MandateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date', 'source_metadata' => 'array'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $user = auth()->user();

        return $query->where($field ?? $this->getRouteKeyName(), $value)
            ->when($user && ! $user->isGlobalAdministrator(), fn ($builder) => $builder->where('unit_id', $user->unit_id));
    }
}
