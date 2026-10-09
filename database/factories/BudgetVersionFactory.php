<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetVersion>
 */
class BudgetVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'created_by' => User::factory(),
            'version' => 1,
            'status' => 'SUBMITTED',
            'total' => 25_000_000,
            'checksum' => hash('sha256', fake()->uuid()),
            'is_immutable' => true,
            'submitted_at' => now(),
        ];
    }
}
