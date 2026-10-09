<?php

namespace Database\Factories;

use App\Enums\LpjStatus;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LpjVersion>
 */
class LpjVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lpj_id' => Lpj::factory(),
            'created_by' => User::factory(),
            'version' => 1,
            'status' => LpjStatus::Submitted,
            'snapshot' => ['activity' => ['title' => fake()->sentence()]],
            'checksum' => hash('sha256', fake()->uuid()),
            'is_immutable' => true,
        ];
    }
}
