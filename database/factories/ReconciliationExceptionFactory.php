<?php

namespace Database\Factories;

use App\Models\Reconciliation;
use App\Models\ReconciliationException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationException>
 */
class ReconciliationExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliation_id' => Reconciliation::factory(),
            'owner_id' => User::factory(),
            'category' => 'AMOUNT_VARIANCE',
            'severity' => 'MEDIUM',
            'status' => 'OPEN',
            'detail' => 'Nilai transaksi tidak sama dengan baris anggaran.',
            'is_approved_exception' => false,
            'due_at' => now()->addDays(3),
        ];
    }
}
