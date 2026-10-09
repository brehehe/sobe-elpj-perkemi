<?php

namespace App\Models;

use Database\Factories\KpiResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kpi_definition_id', 'evidence_id', 'actual', 'achievement_percent', 'notes'])]
class KpiResult extends Model
{
    /** @use HasFactory<KpiResultFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'actual' => 'decimal:4',
            'achievement_percent' => 'decimal:2',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}
