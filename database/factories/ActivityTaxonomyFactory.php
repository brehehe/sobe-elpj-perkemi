<?php

namespace Database\Factories;

use App\Models\ActivityTaxonomy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTaxonomy>
 */
class ActivityTaxonomyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TYPE-###'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'comparable_factors' => ['jumlah peserta', 'durasi', 'output'],
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
