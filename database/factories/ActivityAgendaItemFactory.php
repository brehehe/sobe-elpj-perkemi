<?php

namespace Database\Factories;

use App\Enums\ActivityAgendaStatus;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityAgendaItem>
 */
class ActivityAgendaItemFactory extends Factory
{
    protected $model = ActivityAgendaItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 week', '+1 week');

        return [
            'activity_id' => Activity::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'location' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => ActivityAgendaStatus::Planned,
        ];
    }
}
