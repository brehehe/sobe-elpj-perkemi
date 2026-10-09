<?php

namespace Database\Factories;

use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\LpjVersion;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Verification>
 */
class VerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lpj_version_id' => LpjVersion::factory(),
            'stage' => VerificationStage::Administration,
            'status' => VerificationStatus::Pending,
        ];
    }
}
