<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\Approval;
use App\Models\LpjVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
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
            'decided_by' => User::factory(),
            'level' => ApprovalLevel::Approver,
            'decision' => ApprovalDecision::Approved,
            'authority_policy_version' => 'lpj-approval-authority-v1',
            'authority_reference' => 'DIRECT:APPROVER',
            'authority_snapshot' => ['source' => 'DIRECT'],
            'decision_pack' => ['version' => 1],
            'decision_pack_checksum' => hash('sha256', '{"version":1}'),
            'lpj_checksum' => hash('sha256', fake()->uuid()),
            'signature_hash' => hash('sha256', fake()->uuid()),
            'decided_at' => now(),
        ];
    }
}
