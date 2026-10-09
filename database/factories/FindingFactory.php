<?php

namespace Database\Factories;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\LpjVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
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
            'created_by' => User::factory(),
            'code' => fake()->unique()->bothify('TM-2026-####'),
            'severity' => 'MEDIUM',
            'status' => FindingStatus::Open,
            'title' => fake()->sentence(),
            'detail' => fake()->paragraph(),
            'due_at' => now()->addWeek(),
        ];
    }
}
