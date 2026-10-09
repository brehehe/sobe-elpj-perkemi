<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\KpiDefinition;
use App\Models\KpiResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiResult>
 */
class KpiResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kpi_definition_id' => KpiDefinition::factory(),
            'evidence_id' => Evidence::factory(),
            'actual' => 95,
            'achievement_percent' => 105.56,
            'notes' => fake()->sentence(),
        ];
    }
}
