<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Mandate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'mandate_id' => Mandate::factory(),
            'pic_id' => User::factory(),
            'code' => fake()->unique()->bothify('KGT-2026-###'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(2),
            'status' => ActivityStatus::Completed,
            'budget_ceiling' => fake()->numberBetween(10, 100) * 1000000,
            'plan_version' => 1,
            'budget_version' => 1,
            'plan_snapshot' => [
                'objective' => fake()->sentence(),
                'target_participants' => 100,
                'kpis' => [[
                    'name' => 'Completion',
                    'definition' => 'Persentase agenda yang selesai sesuai rencana.',
                    'baseline' => 80,
                    'target' => 90,
                    'unit' => 'persen',
                    'formula' => 'HIGHER_IS_BETTER',
                    'weight' => 1,
                ]],
            ],
            'budget_snapshot' => ['version' => 1],
        ];
    }
}
