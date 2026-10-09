<?php

namespace Database\Factories;

use App\Enums\ApprovalLevel;
use App\Models\ApprovalDelegation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delegate_id' => User::factory(),
            'granted_by' => User::factory(),
            'unit_id' => Unit::factory(),
            'level' => ApprovalLevel::Approver,
            'max_amount' => null,
            'reference' => 'DLG-'.fake()->unique()->numerify('######'),
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ];
    }
}
