<?php

namespace App\Models;

use App\Enums\KpiFormula;
use Database\Factories\KpiDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['evaluation_id', 'position', 'name', 'definition', 'unit', 'baseline', 'target', 'period_start', 'period_end', 'formula', 'formula_version', 'weight'])]
class KpiDefinition extends Model
{
    /** @use HasFactory<KpiDefinitionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'baseline' => 'decimal:4',
            'target' => 'decimal:4',
            'period_start' => 'date',
            'period_end' => 'date',
            'formula' => KpiFormula::class,
            'weight' => 'decimal:4',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(KpiResult::class);
    }
}
