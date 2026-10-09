<?php

namespace Database\Factories;

use App\Enums\EvaluationStatus;
use App\Models\Activity;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
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
            'created_by' => User::factory(),
            'version' => 1,
            'status' => EvaluationStatus::Accepted,
            'summary' => fake()->paragraph(),
            'outcomes' => fake()->sentence(),
            'completion_percent' => fake()->randomFloat(2, 80, 100),
            'satisfaction_score' => fake()->randomFloat(2, 3.5, 5),
            'lessons_learned' => fake()->sentence(),
            'successful_practices' => fake()->sentence(),
            'improvement_areas' => fake()->sentence(),
            'score' => fake()->randomFloat(2, 70, 100),
            'metrics' => ['attendance_rate' => 90],
            'recommendations' => [fake()->sentence()],
            'formula_version' => 'kpi-achievement-v1',
            'accepted_at' => now(),
        ];
    }
}
