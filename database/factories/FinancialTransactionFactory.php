<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
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
            'recorded_by' => User::factory(),
            'source' => 'MANUAL',
            'external_id' => fake()->unique()->uuid(),
            'reference_number' => fake()->bothify('TRX-########'),
            'account_code' => '521211',
            'type' => 'EXPENSE',
            'description' => fake()->sentence(),
            'transaction_date' => now()->subDay(),
            'amount' => fake()->numberBetween(1, 20) * 100000,
            'currency' => 'IDR',
            'status' => 'POSTED',
        ];
    }
}
