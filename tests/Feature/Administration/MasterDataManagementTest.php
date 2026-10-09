<?php

namespace Tests\Feature\Administration;

use App\Enums\KpiFormula;
use App\Models\ActivityTaxonomy;
use App\Models\KpiCatalog;
use App\Models\PlanVersion;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_permissions_separate_local_unit_management_from_global_master_data(): void
    {
        $unit = Unit::factory()->create();
        $administrator = $this->userWithRole('Administrator', $unit);

        $this->actingAs($administrator)->get('/administration/units')->assertOk();
        $this->actingAs($administrator)->get('/administration/taxonomies')->assertForbidden();
        $this->actingAs($administrator)->get('/administration/kpis')->assertForbidden();
    }

    public function test_local_administrator_sees_and_manages_only_their_unit_subtree(): void
    {
        $province = Unit::factory()->create(['code' => 'JABAR', 'name' => 'Jawa Barat', 'type' => 'PROVINSI']);
        $city = Unit::factory()->create(['parent_id' => $province->id, 'code' => 'BANDUNG', 'type' => 'KOTA']);
        $hidden = Unit::factory()->create(['code' => 'BALI', 'type' => 'PROVINSI']);
        $administrator = $this->userWithRole('Administrator', $province);

        $this->actingAs($administrator)->get('/administration/units')
            ->assertInertia(fn (Assert $page) => $page
                ->component('administration/units')
                ->where('scope.is_global', false)
                ->where('summary.total', 2)
                ->has('units', 1)
                ->where('units.0.id', $province->id)
                ->where('units.0.children.0.id', $city->id)
                ->where('options', fn ($units): bool => $units->where('id', $hidden->id)->isEmpty()));

        $this->actingAs($administrator)->post('/administration/units', [
            'parent_id' => $city->id,
            'code' => 'dojo-bandung-01',
            'name' => '  Dojo   Bandung 01 ',
            'type' => 'DOJO',
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('units', ['parent_id' => $city->id, 'code' => 'DOJO-BANDUNG-01', 'name' => 'Dojo Bandung 01']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $administrator->id, 'event_type' => 'unit.created', 'authority' => 'admin.manage-units']);
    }

    public function test_unit_validation_blocks_cross_scope_cycle_and_unsafe_deactivation(): void
    {
        $province = Unit::factory()->create(['type' => 'PROVINSI']);
        $city = Unit::factory()->create(['parent_id' => $province->id, 'type' => 'KOTA']);
        $dojo = Unit::factory()->create(['parent_id' => $city->id, 'type' => 'DOJO']);
        $otherProvince = Unit::factory()->create(['type' => 'PROVINSI']);
        $administrator = $this->userWithRole('Administrator', $province);

        $this->actingAs($administrator)->from('/administration/units')->post('/administration/units', [
            'parent_id' => $otherProvince->id,
            'code' => 'BLOCKED',
            'name' => 'Blocked',
            'type' => 'KOTA',
            'is_active' => true,
        ])->assertRedirect('/administration/units')->assertSessionHasErrors('parent_id');

        $this->actingAs($administrator)->from('/administration/units')->patch('/administration/units/'.$city->id, [
            'parent_id' => $dojo->id,
            'code' => $city->code,
            'name' => $city->name,
            'type' => $city->type,
            'is_active' => true,
        ])->assertRedirect('/administration/units')->assertSessionHasErrors('parent_id');

        $this->actingAs($administrator)->from('/administration/units')->patch('/administration/units/'.$province->id, [
            'parent_id' => null,
            'code' => $province->code,
            'name' => $province->name,
            'type' => $province->type,
            'is_active' => false,
        ])->assertRedirect('/administration/units')->assertSessionHasErrors('is_active');
    }

    public function test_super_admin_creates_and_updates_activity_taxonomy_with_audit_event(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());

        $this->actingAs($superAdmin)->post('/administration/taxonomies', [
            'code' => 'seminar',
            'name' => ' Seminar Organisasi ',
            'description' => 'Forum penguatan organisasi.',
            'comparable_factors' => ['tema', 'durasi', 'jumlah peserta'],
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $taxonomy = ActivityTaxonomy::query()->where('code', 'SEMINAR')->firstOrFail();
        $this->assertSame(['tema', 'durasi', 'jumlah peserta'], $taxonomy->comparable_factors);
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $taxonomy->id, 'event_type' => 'activity_taxonomy.created']);

        $this->actingAs($superAdmin)->patch('/administration/taxonomies/'.$taxonomy->id, [
            'code' => 'SEMINAR',
            'name' => 'Seminar & Lokakarya',
            'description' => 'Forum penguatan organisasi dan kompetensi.',
            'comparable_factors' => ['tema', 'durasi', 'jumlah peserta', 'output'],
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Seminar & Lokakarya', $taxonomy->fresh()->name);
    }

    public function test_used_taxonomy_cannot_be_renamed_or_deactivated(): void
    {
        $superAdmin = $this->userWithRole('Super Admin', Unit::factory()->create());
        $taxonomy = ActivityTaxonomy::query()->where('code', 'PEMBINAAN')->firstOrFail();
        PlanVersion::factory()->create(['activity_type' => $taxonomy->code]);

        $this->actingAs($superAdmin)->from('/administration/taxonomies')->patch('/administration/taxonomies/'.$taxonomy->id, [
            'code' => 'PEMBINAAN-BARU',
            'name' => $taxonomy->name,
            'description' => $taxonomy->description,
            'comparable_factors' => $taxonomy->comparable_factors,
            'is_active' => false,
        ])->assertRedirect('/administration/taxonomies')->assertSessionHasErrors(['code', 'is_active']);
    }

    public function test_super_admin_manages_kpi_catalog_and_planning_receives_master_options(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole('Super Admin', $unit);
        $taxonomy = ActivityTaxonomy::query()->where('code', 'PEMBINAAN')->firstOrFail();

        $this->actingAs($superAdmin)->post('/administration/kpis', [
            'activity_taxonomy_id' => $taxonomy->id,
            'code' => 'kpi-kehadiran',
            'name' => 'Persentase kehadiran',
            'definition' => 'Jumlah peserta hadir dibanding peserta terdaftar.',
            'unit' => 'persen',
            'formula' => KpiFormula::HigherIsBetter->value,
            'default_weight' => 20,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $catalog = KpiCatalog::query()->where('code', 'KPI-KEHADIRAN')->firstOrFail();
        $this->assertDatabaseHas('audit_events', ['auditable_id' => $catalog->id, 'event_type' => 'kpi_catalog.created']);

        $this->actingAs($superAdmin)->get('/planning/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning/create')
                ->where('activityTypes', fn ($types): bool => $types->where('code', 'PEMBINAAN')->isNotEmpty())
                ->where('kpiCatalogs', fn ($catalogs): bool => $catalogs->where('code', 'KPI-KEHADIRAN')->where('activity_type', 'PEMBINAAN')->isNotEmpty()));
    }

    public function test_inactive_taxonomy_is_not_offered_to_planning(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        ActivityTaxonomy::factory()->create(['code' => 'NONAKTIF', 'is_active' => false]);

        $this->actingAs($owner)->get('/planning/create')
            ->assertInertia(fn (Assert $page) => $page
                ->where('activityTypes', fn ($types): bool => $types->where('code', 'NONAKTIF')->isEmpty()));
    }

    public function test_master_data_delete_actions_deactivate_records_without_removing_history(): void
    {
        $actorUnit = Unit::factory()->create();
        $targetUnit = Unit::factory()->create(['is_active' => true]);
        $superAdmin = $this->userWithRole('Super Admin', $actorUnit);
        $taxonomy = ActivityTaxonomy::factory()->create(['is_active' => true]);
        $catalog = KpiCatalog::factory()->create(['activity_taxonomy_id' => $taxonomy->id, 'is_active' => true]);

        $this->actingAs($superAdmin)->delete("/administration/kpis/{$catalog->id}")->assertRedirect('/administration/kpis');
        $this->actingAs($superAdmin)->delete("/administration/taxonomies/{$taxonomy->id}")->assertRedirect('/administration/taxonomies');
        $this->actingAs($superAdmin)->delete("/administration/units/{$targetUnit->id}")->assertRedirect('/administration/units');

        $this->assertDatabaseHas('kpi_catalogs', ['id' => $catalog->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_taxonomies', ['id' => $taxonomy->id, 'is_active' => false]);
        $this->assertDatabaseHas('units', ['id' => $targetUnit->id, 'is_active' => false]);
    }
}
