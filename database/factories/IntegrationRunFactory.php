<?php

namespace Database\Factories;

use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Models\IntegrationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationRun>
 */
class IntegrationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'SIMKEU',
            'direction' => 'INBOUND',
            'idempotency_key' => fake()->unique()->uuid(),
            'status' => IntegrationStatus::Pending,
            'contract_version' => 'finance-transaction-v1',
            'correlation_id' => fake()->uuid(),
            'retry_status' => IntegrationRetryStatus::NotRequired,
            'records_received' => 1,
            'payload_hash' => hash('sha256', fake()->uuid()),
            'last_attempted_at' => now(),
        ];
    }
}
