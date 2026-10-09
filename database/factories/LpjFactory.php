<?php

namespace Database\Factories;

use App\Enums\LpjStatus;
use App\Models\Activity;
use App\Models\Lpj;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lpj>
 */
class LpjFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'owner_id' => User::factory(),
            'status' => LpjStatus::Draft,
            'current_version' => 0,
            'total_amount' => 0,
            'executive_summary' => fake()->paragraph(),
            'implementation_narrative' => fake()->paragraph(),
            'outcome_narrative' => fake()->paragraph(),
            'financial_narrative' => fake()->paragraph(),
            'constraints' => fake()->paragraph(),
            'follow_up_plan' => fake()->paragraph(),
            'draft_revision' => 0,
        ];
    }
}
