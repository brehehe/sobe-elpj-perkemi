<?php

namespace Database\Factories;

use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use App\Models\Activity;
use App\Models\ActivityExecutionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityExecutionRecord>
 */
class ActivityExecutionRecordFactory extends Factory
{
    protected $model = ActivityExecutionRecord::class;

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
            'type' => ActivityExecutionType::Note,
            'title' => fake()->sentence(4),
            'details' => fake()->paragraph(),
            'status' => ActivityExecutionStatus::Recorded,
            'occurred_at' => now(),
        ];
    }
}
