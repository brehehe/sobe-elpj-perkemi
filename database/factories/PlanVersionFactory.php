<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'created_by' => User::factory(),
            'version' => 1,
            'status' => 'SUBMITTED',
            'activity_type' => 'PENATARAN',
            'objective' => fake()->sentence(),
            'output' => fake()->sentence(),
            'outcome' => fake()->sentence(),
            'target_participants' => 100,
            'kpis' => [[
                'name' => 'Completion',
                'definition' => 'Persentase agenda yang selesai sesuai ruang lingkup planning.',
                'baseline' => 80,
                'target' => 90,
                'unit' => 'persen',
                'formula' => 'HIGHER_IS_BETTER',
                'weight' => 1,
                'period_start' => now()->subDays(3)->toDateString(),
                'period_end' => now()->subDays(2)->toDateString(),
            ]],
            'schedule' => ['duration_days' => 3],
            'resources' => [],
            'risks' => [['risk' => 'Keterlambatan evidence', 'mitigation' => 'Checkpoint harian']],
            'checksum' => hash('sha256', fake()->uuid()),
            'is_immutable' => true,
            'submitted_at' => now(),
        ];
    }
}
