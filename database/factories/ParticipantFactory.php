<?php

namespace Database\Factories;

use App\Enums\ParticipantRole;
use App\Models\Activity;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'organization' => fake()->company(),
            'role' => ParticipantRole::Participant,
            'attendance_status' => 'ATTENDED',
            'source' => 'MANUAL',
        ];
    }
}
