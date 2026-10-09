<?php

namespace Database\Factories;

use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectiveAction>
 */
class CorrectiveActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'submitted_by' => User::factory(),
            'action' => fake()->paragraph(),
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ];
    }
}
