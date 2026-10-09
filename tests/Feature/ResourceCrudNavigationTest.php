<?php

namespace Tests\Feature;

use App\Models\ActivityTaxonomy;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\KpiCatalog;
use App\Models\Mandate;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ResourceCrudNavigationTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_super_admin_can_open_table_create_show_and_edit_pages(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);
        $managedUser = $this->userWithRole('Owner/PIC', $unit);
        $mandate = Mandate::factory()->create([
            'unit_id' => $unit->id,
            'created_by' => $managedUser->id,
            'status' => 'ACTIVE',
        ]);
        $plan = Plan::factory()->create([
            'mandate_id' => $mandate->id,
            'unit_id' => $unit->id,
            'owner_id' => $managedUser->id,
            'status' => 'RETURNED',
            'current_version' => 1,
        ]);
        PlanVersion::factory()->create([
            'plan_id' => $plan->id,
            'created_by' => $managedUser->id,
            'status' => 'RETURNED',
            'version' => 1,
        ]);
        $budget = Budget::factory()->create([
            'plan_id' => $plan->id,
            'unit_id' => $unit->id,
            'owner_id' => $managedUser->id,
            'status' => 'RETURNED',
            'current_version' => 1,
        ]);
        BudgetVersion::factory()->create([
            'budget_id' => $budget->id,
            'created_by' => $managedUser->id,
            'status' => 'RETURNED',
            'version' => 1,
        ]);
        $taxonomy = ActivityTaxonomy::factory()->create();
        $catalog = KpiCatalog::factory()->create(['activity_taxonomy_id' => $taxonomy->id]);

        $this->actingAs($superAdmin)->get('/mandates')->assertInertia(fn (Assert $page) => $page->component('mandates/index'));
        $this->actingAs($superAdmin)->get('/mandates/create')->assertInertia(fn (Assert $page) => $page->component('mandates/create'));
        $this->actingAs($superAdmin)->get("/mandates/{$mandate->id}")->assertInertia(fn (Assert $page) => $page->component('mandates/show'));
        $this->actingAs($superAdmin)->get("/mandates/{$mandate->id}/edit")->assertInertia(fn (Assert $page) => $page->component('mandates/edit'));

        $this->actingAs($superAdmin)->get('/planning')->assertInertia(fn (Assert $page) => $page->component('planning/index'));
        $this->actingAs($superAdmin)->get('/planning/create')->assertInertia(fn (Assert $page) => $page->component('planning/create'));
        $this->actingAs($superAdmin)->get("/planning/{$plan->id}")->assertInertia(fn (Assert $page) => $page->component('planning/show'));
        $this->actingAs($superAdmin)->get("/planning/{$plan->id}/edit")->assertInertia(fn (Assert $page) => $page->component('planning/edit'));

        $this->actingAs($superAdmin)->get('/budgets')->assertInertia(fn (Assert $page) => $page->component('budgets/index'));
        $this->actingAs($superAdmin)->get('/budgets/create')->assertInertia(fn (Assert $page) => $page->component('budgets/create'));
        $this->actingAs($superAdmin)->get("/budgets/{$budget->id}")->assertInertia(fn (Assert $page) => $page->component('budgets/show'));
        $this->actingAs($superAdmin)->get("/budgets/{$budget->id}/edit")->assertInertia(fn (Assert $page) => $page->component('budgets/edit'));

        $this->actingAs($superAdmin)->get('/administration/users')->assertInertia(fn (Assert $page) => $page->component('administration/users'));
        $this->actingAs($superAdmin)->get('/administration/users/create')->assertInertia(fn (Assert $page) => $page->component('administration/users/create'));
        $this->actingAs($superAdmin)->get("/administration/users/{$managedUser->id}")->assertInertia(fn (Assert $page) => $page->component('administration/users/show'));
        $this->actingAs($superAdmin)->get("/administration/users/{$managedUser->id}/edit")->assertInertia(fn (Assert $page) => $page->component('administration/users/edit'));

        $this->actingAs($superAdmin)->get('/administration/units/create')->assertInertia(fn (Assert $page) => $page->component('administration/units/create'));
        $this->actingAs($superAdmin)->get("/administration/units/{$unit->id}")->assertInertia(fn (Assert $page) => $page->component('administration/units/show'));
        $this->actingAs($superAdmin)->get("/administration/units/{$unit->id}/edit")->assertInertia(fn (Assert $page) => $page->component('administration/units/edit'));
        $this->actingAs($superAdmin)->get('/administration/taxonomies/create')->assertInertia(fn (Assert $page) => $page->component('administration/taxonomies/create'));
        $this->actingAs($superAdmin)->get("/administration/taxonomies/{$taxonomy->id}")->assertInertia(fn (Assert $page) => $page->component('administration/taxonomies/show'));
        $this->actingAs($superAdmin)->get("/administration/taxonomies/{$taxonomy->id}/edit")->assertInertia(fn (Assert $page) => $page->component('administration/taxonomies/edit'));
        $this->actingAs($superAdmin)->get('/administration/kpis/create')->assertInertia(fn (Assert $page) => $page->component('administration/kpis/create'));
        $this->actingAs($superAdmin)->get("/administration/kpis/{$catalog->id}")->assertInertia(fn (Assert $page) => $page->component('administration/kpis/show'));
        $this->actingAs($superAdmin)->get("/administration/kpis/{$catalog->id}/edit")->assertInertia(fn (Assert $page) => $page->component('administration/kpis/edit'));
    }

    public function test_legacy_workspace_mandates_url_redirects_to_resource_table(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);

        $this->actingAs($superAdmin)->get('/workspace/mandates')->assertRedirect('/mandates');
    }
}
