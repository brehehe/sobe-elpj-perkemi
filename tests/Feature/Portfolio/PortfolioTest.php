<?php

namespace Tests\Feature\Portfolio;

use App\Enums\LpjStatus;
use App\Enums\VerificationStatus;
use App\Models\Finding;
use App\Models\LpjVersion;
use App\Models\Unit;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/portfolio')->assertRedirect('/login');
    }

    public function test_user_without_lpj_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/portfolio')->assertForbidden();
    }

    public function test_local_user_only_sees_their_unit_portfolio(): void
    {
        $visible = $this->completeWorkflow();
        $hidden = $this->completeWorkflow();
        $visible['activity']->update(['budget_ceiling' => 2_000_000]);
        $visible['activity']->transactions()->firstOrFail()->update(['amount' => 1_250_000]);
        $hidden['activity']->update(['code' => 'KGT-HIDDEN-001', 'budget_ceiling' => 8_000_000]);

        $this->actingAs($visible['owner'])->get('/portfolio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portfolio/index')
                ->where('scope.can_view_across_units', false)
                ->where('scope.selected_unit.id', $visible['unit']->id)
                ->where('summary.total_lpjs', 1)
                ->where('summary.budget', 2_000_000)
                ->where('summary.actual', 1_250_000)
                ->has('units', 0)
                ->has('portfolio.data', 1)
                ->where('portfolio.data.0.activity.code', $visible['activity']->code)
                ->missing('portfolio.data.1'));

        $this->actingAs($visible['owner'])->get('/portfolio')
            ->assertDontSee('KGT-HIDDEN-001');
    }

    public function test_pb_verifier_can_drill_from_parent_into_descendant_units(): void
    {
        $root = Unit::factory()->create(['code' => 'PB-ROOT', 'name' => 'PB Nasional', 'type' => 'PUSAT']);
        $province = Unit::factory()->create(['parent_id' => $root->id, 'code' => 'PROV-01', 'name' => 'Pengprov Utama', 'type' => 'PROVINSI']);
        $branch = Unit::factory()->create(['parent_id' => $province->id, 'code' => 'CAB-01', 'name' => 'Pengkot Utama', 'type' => 'KOTA']);
        $provinceWorkflow = $this->completeWorkflow($province);
        $branchWorkflow = $this->completeWorkflow($branch);
        $pbVerifier = $this->userWithRole('PB Verifier', $root);

        $this->actingAs($pbVerifier)->get('/portfolio?unit='.$root->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_across_units', true)
                ->where('scope.selected_unit.id', $root->id)
                ->where('summary.total_lpjs', 2)
                ->has('scope.breadcrumbs', 1)
                ->has('units', 1)
                ->where('units.0.id', $province->id)
                ->where('units.0.total_lpjs', 2)
                ->where('units.0.activity_count', 2)
                ->has('portfolio.data', 2)
                ->where('portfolio.data.0.activity.id', fn (string $id): bool => in_array($id, [$provinceWorkflow['activity']->id, $branchWorkflow['activity']->id], true)));
    }

    public function test_local_user_cannot_probe_another_unit_through_the_filter(): void
    {
        $visible = $this->completeWorkflow();
        $hiddenUnit = Unit::factory()->create();

        $this->actingAs($visible['owner'])
            ->get('/portfolio?unit='.$hiddenUnit->id)
            ->assertNotFound();
    }

    public function test_query_status_and_sort_filters_are_applied_server_side(): void
    {
        $first = $this->completeWorkflow();
        $second = $this->completeWorkflow($first['unit'], $first['owner']);
        $first['activity']->update(['code' => 'KGT-ALPHA-001', 'title' => 'Program Alpha']);
        $first['lpj']->update(['status' => LpjStatus::Archived]);
        $first['activity']->transactions()->firstOrFail()->update(['amount' => 5_000_000]);
        $second['activity']->update(['code' => 'KGT-BETA-001', 'title' => 'Program Beta']);
        $second['lpj']->update(['status' => LpjStatus::Draft]);

        $this->actingAs($first['owner'])
            ->get('/portfolio?q=alpha&status=ARCHIVED&sort=actual_desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'alpha')
                ->where('filters.status', 'ARCHIVED')
                ->where('filters.sort', 'actual_desc')
                ->has('portfolio.data', 1)
                ->where('portfolio.data.0.activity.code', 'KGT-ALPHA-001'));
    }

    public function test_invalid_sort_is_rejected_with_a_user_visible_error(): void
    {
        $workflow = $this->completeWorkflow();

        $this->actingAs($workflow['owner'])
            ->from('/portfolio')
            ->get('/portfolio?sort=drop-table')
            ->assertRedirect('/portfolio')
            ->assertSessionHasErrors(['sort' => 'Urutan portfolio tidak dikenali.']);
    }

    public function test_rows_report_current_verification_and_finding_counts(): void
    {
        $workflow = $this->completeWorkflow();
        $version = LpjVersion::factory()->create([
            'lpj_id' => $workflow['lpj']->id,
            'created_by' => $workflow['owner']->id,
            'version' => 1,
        ]);
        $workflow['lpj']->update(['current_version' => 1, 'status' => LpjStatus::InVerification]);
        Verification::factory()->create([
            'lpj_version_id' => $version->id,
            'status' => VerificationStatus::Verified,
        ]);
        Finding::factory()->create([
            'lpj_version_id' => $version->id,
            'created_by' => $workflow['owner']->id,
        ]);

        $this->actingAs($workflow['owner'])->get('/portfolio')
            ->assertInertia(fn (Assert $page) => $page
                ->where('portfolio.data.0.verification.completed', 1)
                ->where('portfolio.data.0.verification.total', 4)
                ->where('portfolio.data.0.open_findings', 1)
                ->where('summary.open_findings', 1));
    }
}
