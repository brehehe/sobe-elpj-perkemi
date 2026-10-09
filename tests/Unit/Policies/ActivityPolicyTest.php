<?php

namespace Tests\Unit\Policies;

use App\Models\Unit;
use App\Policies\ActivityPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ActivityPolicyTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_activity_policy_combines_capability_and_unit_scope(): void
    {
        $workflow = $this->completeWorkflow();
        $otherUnit = Unit::factory()->create();
        $otherOwner = $this->userWithRole('Owner/PIC', $otherUnit);
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);
        $policy = new ActivityPolicy;

        $this->assertTrue($policy->update($workflow['owner'], $workflow['activity']));
        $this->assertFalse($policy->update($otherOwner, $workflow['activity']));
        $this->assertFalse($policy->update($verifier, $workflow['activity']));
    }
}
