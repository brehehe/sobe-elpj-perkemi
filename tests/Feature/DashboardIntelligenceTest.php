<?php

namespace Tests\Feature;

use App\Enums\AiReviewStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class DashboardIntelligenceTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_dashboard_exposes_scoped_real_intelligence_instead_of_demo_copy(): void
    {
        $workflow = $this->completeWorkflow();
        $otherWorkflow = $this->completeWorkflow(Unit::factory()->create());
        $this->review($workflow, 'RISK_REVIEW', 'Risiko evidence perlu divalidasi.', 'local-rules-engine');
        $this->review($workflow, 'NEXT_ACTIVITY', 'Skenario kegiatan berikutnya tersedia.', 'gpt-test');
        $this->review($otherWorkflow, 'RISK_REVIEW', 'Data unit lain tidak boleh tampil.', 'gpt-test');

        $this->actingAs($workflow['owner'])->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('canViewIntelligence', true)
                ->has('intelligenceBriefs', 1)
                ->where('intelligenceBriefs.0.summary', 'Risiko evidence perlu divalidasi.')
                ->where('intelligenceBriefs.0.engine', 'RULE_BASED')
                ->has('nextActivityRecommendations', 1)
                ->where('nextActivityRecommendations.0.summary', 'Skenario kegiatan berikutnya tersedia.')
                ->where('nextActivityRecommendations.0.engine', 'OPENAI'));
    }

    /** @param array{activity: Activity, owner: User} $workflow */
    private function review(array $workflow, string $type, string $summary, string $model): AiReview
    {
        return AiReview::factory()->create([
            'activity_id' => $workflow['activity']->id,
            'requested_by' => $workflow['owner']->id,
            'type' => $type,
            'status' => AiReviewStatus::ReviewRequired,
            'model' => $model,
            'output' => [
                'summary' => $summary,
                'risks' => [],
                'recommendations' => ['Tinjau sumber sebelum digunakan.'],
            ],
            'confidence' => 0.8,
        ]);
    }
}
