<?php

namespace App\Models;

use Database\Factories\ActivityTaxonomyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'comparable_factors', 'is_active', 'is_system'])]
class ActivityTaxonomy extends Model
{
    /** @use HasFactory<ActivityTaxonomyFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'comparable_factors' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function kpiCatalogs(): HasMany
    {
        return $this->hasMany(KpiCatalog::class);
    }
}
