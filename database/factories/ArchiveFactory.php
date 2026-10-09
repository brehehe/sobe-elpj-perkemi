<?php

namespace Database\Factories;

use App\Models\Archive;
use App\Models\User;
use App\Models\VerifiedPack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Archive>
 */
class ArchiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'verified_pack_id' => VerifiedPack::factory(),
            'lpj_version_id' => fn (array $attributes) => VerifiedPack::query()->findOrFail($attributes['verified_pack_id'])->lpj_version_id,
            'archived_by' => User::factory(),
            'archive_number' => fake()->unique()->bothify('ARS-2026-#####'),
            'disk' => 'local',
            'path' => 'archives/test.json',
            'checksum' => hash('sha256', fake()->uuid()),
            'verified_pack_checksum' => fn (array $attributes) => VerifiedPack::query()->findOrFail($attributes['verified_pack_id'])->checksum,
            'public_token' => (string) Str::uuid7(),
            'retention_policy_version' => 'lpj-archive-retention-v1',
            'package_schema_version' => 1,
            'archived_at' => now(),
            'integrity_verified_at' => now(),
            'retention_until' => now()->addYears(10),
        ];
    }
}
