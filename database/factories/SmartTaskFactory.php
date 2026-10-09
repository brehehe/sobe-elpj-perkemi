<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\SmartTask;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmartTask>
 */
class SmartTaskFactory extends Factory
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
            'type' => 'FOLLOW_UP',
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => 'MEDIUM',
            'status' => TaskStatus::Open,
            'source' => 'SYSTEM',
            'due_at' => now()->addWeek(),
        ];
    }
}
