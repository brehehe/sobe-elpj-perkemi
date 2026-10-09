<?php

namespace Database\Factories;

use App\Enums\GovernancePolicyType;
use App\Models\GovernancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GovernancePolicy>
 */
class GovernancePolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => GovernancePolicyType::System,
            'version' => 'system-'.fake()->unique()->numerify('v###'),
            'settings' => [
                'data_classification' => 'Internal',
                'maintenance_message' => null,
                'support_message' => 'Hubungi administrator sistem.',
            ],
            'change_summary' => fake()->sentence(),
            'is_active' => true,
            'created_by' => User::factory(),
            'activated_at' => now(),
        ];
    }
}
