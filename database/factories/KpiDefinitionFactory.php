<?php

namespace Database\Factories;

use App\Enums\KpiFormula;
use App\Models\Evaluation;
use App\Models\KpiDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiDefinition>
 */
class KpiDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'position' => 0,
            'name' => fake()->words(3, true),
            'definition' => fake()->sentence(),
            'unit' => 'persen',
            'baseline' => 0,
            'target' => 90,
            'period_start' => now()->subDays(3)->toDateString(),
            'period_end' => now()->subDays(2)->toDateString(),
            'formula' => KpiFormula::HigherIsBetter,
            'formula_version' => 'kpi-achievement-v1',
            'weight' => 1,
        ];
    }
}
