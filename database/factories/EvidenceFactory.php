<?php

namespace Database\Factories;

use App\Enums\EvidenceScanStatus;
use App\Models\Activity;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
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
            'uploaded_by' => User::factory(),
            'type' => 'REPORT',
            'title' => fake()->sentence(3),
            'disk' => 'local',
            'path' => 'test/evidence.pdf',
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'detected_mime_type' => 'application/pdf',
            'size' => 1024,
            'sha256' => hash('sha256', Str::random()),
            'scan_status' => EvidenceScanStatus::Registered,
            'scan_engine' => 'SIGNATURE',
            'scan_notes' => 'Fixture bukti bersih.',
            'scanned_at' => now(),
            'linked_at' => now(),
        ];
    }
}
