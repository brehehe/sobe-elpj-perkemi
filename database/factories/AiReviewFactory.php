<?php

namespace Database\Factories;

use App\Enums\AiReviewStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReview>
 */
class AiReviewFactory extends Factory
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
            'requested_by' => User::factory(),
            'type' => 'PRECHECK',
            'status' => AiReviewStatus::Queued,
            'prompt_hash' => hash('sha256', fake()->uuid()),
            'input_sources' => ['activity_id'],
        ];
    }
}
