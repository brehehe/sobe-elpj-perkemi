<?php

namespace App\Models;

use App\Enums\KpiFormula;
use Database\Factories\KpiCatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'formula', 'default_weight', 'is_active'])]
class KpiCatalog extends Model
{
    /** @use HasFactory<KpiCatalogFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'formula' => KpiFormula::class,
            'default_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function activityTaxonomy(): BelongsTo
    {
        return $this->belongsTo(ActivityTaxonomy::class);
    }
}
