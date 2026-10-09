<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\Approval;
use App\Models\User;
use App\Models\VerifiedPack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VerifiedPack>
 */
class VerifiedPackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checksum = hash('sha256', fake()->uuid());

        return [
            'approval_id' => Approval::factory()->state([
                'level' => ApprovalLevel::Pb,
                'decision' => ApprovalDecision::Approved,
            ]),
            'lpj_version_id' => fn (array $attributes) => Approval::query()->findOrFail($attributes['approval_id'])->lpj_version_id,
            'issued_by' => User::factory(),
            'verification_number' => fake()->unique()->bothify('VRF-202610-########'),
            'policy_version' => 'lpj-verified-pack-v1',
            'schema_version' => 1,
            'disk' => 'local',
            'path' => 'verified-packs/test.json',
            'checksum' => $checksum,
            'lpj_checksum' => fn (array $attributes) => Approval::query()->findOrFail($attributes['approval_id'])->lpjVersion()->valueOrFail('checksum'),
            'approval_signature_hash' => fn (array $attributes) => Approval::query()->findOrFail($attributes['approval_id'])->signature_hash,
            'public_token' => (string) Str::uuid7(),
            'metadata' => ['reference_type' => 'PUBLIC_TOKEN'],
            'issued_at' => now(),
        ];
    }
}
