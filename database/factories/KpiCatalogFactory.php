<?php

namespace Database\Factories;

use App\Enums\KpiFormula;
use App\Models\ActivityTaxonomy;
use App\Models\KpiCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiCatalog>
 */
class KpiCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_taxonomy_id' => ActivityTaxonomy::factory(),
            'code' => fake()->unique()->bothify('KPI-###'),
            'name' => fake()->sentence(3),
            'definition' => fake()->sentence(),
            'unit' => 'persen',
            'formula' => KpiFormula::HigherIsBetter,
            'default_weight' => 1,
            'is_active' => true,
        ];
    }
}
