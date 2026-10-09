<?php

namespace Database\Factories;

use App\Models\Disposition;
use App\Models\LpjVersion;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disposition>
 */
class DispositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lpj_version_id' => LpjVersion::factory(),
            'unit_id' => Unit::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'subject' => fake()->sentence(5),
            'instructions' => fake()->paragraph(),
            'priority' => 'MEDIUM',
            'status' => 'OPEN',
            'due_at' => now()->addDays(3),
        ];
    }
}
