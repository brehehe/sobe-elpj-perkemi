<?php

namespace Database\Factories;

use App\Models\Mandate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mandate>
 */
class MandateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'created_by' => User::factory(),
            'number' => fake()->unique()->bothify('ST-####/PB/X/2026'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'issued_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(2),
            'status' => 'ACTIVE',
        ];
    }
}
