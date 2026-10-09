<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_transaction_id' => FinancialTransaction::factory(),
            'reconciled_by' => User::factory(),
            'status' => 'MATCHED',
            'expected_amount' => 1_000_000,
            'actual_amount' => 1_000_000,
            'variance' => 0,
            'checks' => ['amount' => true, 'reference' => true, 'evidence' => true],
            'reconciled_at' => now(),
        ];
    }
}
