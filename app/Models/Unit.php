<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'code', 'name', 'type', 'is_active', 'settings'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'settings' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }
}
