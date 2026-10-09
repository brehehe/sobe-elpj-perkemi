<?php

namespace Database\Factories;

use App\Models\Mandate;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mandate_id' => Mandate::factory(),
            'unit_id' => Unit::factory(),
            'owner_id' => User::factory(),
            'code' => fake()->unique()->bothify('PLN-2026-####'),
            'status' => 'SUBMITTED',
            'current_version' => 1,
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'APPROVED', 'approved_at' => now()]);
    }
}
