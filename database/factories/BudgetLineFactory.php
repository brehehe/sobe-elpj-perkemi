<?php

namespace Database\Factories;

use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_version_id' => BudgetVersion::factory(),
            'line_number' => 1,
            'account_code' => '521211',
            'description' => fake()->sentence(4),
            'quantity' => 100,
            'unit' => 'orang',
            'unit_cost' => 75_000,
            'subtotal' => 7_500_000,
        ];
    }
}
