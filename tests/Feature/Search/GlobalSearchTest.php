<?php

namespace Tests\Feature\Search;

use App\Models\Finding;
use App\Models\LpjVersion;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_search_requires_an_authenticated_verified_user(): void
    {
        $this->getJson('/search?q=activity')->assertUnauthorized();
    }

    public function test_search_rejects_queries_outside_the_supported_length(): void
    {
        $workflow = $this->completeWorkflow();

        $this->actingAs($workflow['owner'])->getJson('/search?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->actingAs($workflow['owner'])->getJson('/search?q='.str_repeat('x', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_authorized_user_can_search_workspace_records(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->update(['code' => 'KGT-GARUDA-001', 'title' => 'Operasi Garuda']);
        $workflow['activity']->transactions()->firstOrFail()->update([
            'reference_number' => 'TRX-GARUDA-001',
            'description' => 'Pembayaran Garuda',
        ]);
        $workflow['activity']->evidence()->firstOrFail()->update([
            'title' => 'Bukti Garuda',
            'original_name' => 'garuda.pdf',
        ]);
        $version = LpjVersion::factory()->create([
            'lpj_id' => $workflow['lpj']->id,
            'created_by' => $workflow['owner']->id,
        ]);
        Finding::factory()->create([
            'lpj_version_id' => $version->id,
            'created_by' => $workflow['owner']->id,
            'code' => 'TM-GARUDA-001',
            'title' => 'Temuan Garuda',
        ]);

        $response = $this->actingAs($workflow['owner'])->getJson('/search?q=garuda');

        $response->assertOk()
            ->assertJsonPath('meta.query', 'garuda')
            ->assertJsonPath('meta.limit_per_type', 5)
            ->assertJsonCount(5, 'data');

        $types = collect($response->json('data'))->pluck('type');
        $this->assertEqualsCanonicalizing(
            ['activity', 'lpj', 'transaction', 'evidence', 'finding'],
            $types->all(),
        );
        $response->assertJsonFragment([
            'type' => 'activity',
            'title' => 'KGT-GARUDA-001 · Operasi Garuda',
            'href' => route('activities.show', $workflow['activity'], false),
        ])->assertJsonFragment([
            'type' => 'transaction',
            'title' => 'TRX-GARUDA-001 · Pembayaran Garuda',
            'href' => route('activities.show', $workflow['activity'], false).'#activity-transactions',
        ])->assertJsonFragment([
            'type' => 'finding',
            'title' => 'TM-GARUDA-001 · Temuan Garuda',
            'href' => route('lpjs.show', $workflow['lpj'], false).'#lpj-findings',
        ]);
    }

    public function test_non_global_user_never_receives_records_from_another_unit(): void
    {
        $visible = $this->completeWorkflow();
        $hidden = $this->completeWorkflow(Unit::factory()->create());
        $visible['activity']->update(['title' => 'Program Nusantara']);
        $hidden['activity']->update(['title' => 'Program Nusantara Rahasia']);
        $visible['activity']->transactions()->firstOrFail()->update(['description' => 'Belanja Nusantara']);
        $hidden['activity']->transactions()->firstOrFail()->update(['description' => 'Belanja Nusantara Rahasia']);
        $visible['activity']->evidence()->firstOrFail()->update(['title' => 'Bukti Nusantara']);
        $hidden['activity']->evidence()->firstOrFail()->update(['title' => 'Bukti Nusantara Rahasia']);

        $response = $this->actingAs($visible['owner'])->getJson('/search?q=Nusantara');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains('activity-'.$visible['activity']->id));
        $this->assertFalse($ids->contains('activity-'.$hidden['activity']->id));
        $this->assertStringNotContainsString($hidden['activity']->id, $response->getContent());
    }

    public function test_results_are_limited_to_resource_types_the_user_may_view(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['activity']->update(['title' => 'Agenda Cakra']);
        $workflow['activity']->evidence()->firstOrFail()->update(['title' => 'Bukti Cakra']);
        $workflow['lpj']->update(['executive_summary' => 'Cakra']);
        $user = User::factory()->forUnit($workflow['unit'])->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findByName('activities.view'));

        $response = $this->actingAs($user)->getJson('/search?q=Cakra');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type')->unique();
        $this->assertTrue($types->contains('activity'));
        $this->assertFalse($types->contains('lpj'));
        $this->assertFalse($types->contains('evidence'));
        $this->assertFalse($types->contains('finding'));
        $this->assertFalse($types->contains('unit'));
    }

    public function test_hierarchy_viewer_can_search_units(): void
    {
        $workflow = $this->completeWorkflow();
        $workflow['unit']->update(['code' => 'UNIT-RAJAWALI', 'name' => 'Pengprov Rajawali']);
        $auditor = $this->userWithRole('Auditor', $workflow['unit']);

        $this->actingAs($auditor)->getJson('/search?q=Rajawali')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'unit-'.$workflow['unit']->id,
                'type' => 'unit',
                'title' => 'UNIT-RAJAWALI · Pengprov Rajawali',
                'href' => route('organization.hierarchy', absolute: false).'#unit-'.$workflow['unit']->id,
            ]);
    }

    public function test_sql_like_input_does_not_bypass_unit_scope(): void
    {
        $visible = $this->completeWorkflow();
        $hidden = $this->completeWorkflow(Unit::factory()->create());

        $response = $this->actingAs($visible['owner'])->getJson('/search?q='.urlencode("' OR 1=1 --"));

        $response->assertOk();
        $this->assertStringNotContainsString($hidden['activity']->id, $response->getContent());
    }

    public function test_search_is_rate_limited_per_user(): void
    {
        $workflow = $this->completeWorkflow();

        for ($request = 1; $request <= 90; $request++) {
            $this->actingAs($workflow['owner'])->getJson('/search?q=activity')->assertOk();
        }

        $this->actingAs($workflow['owner'])->getJson('/search?q=activity')->assertTooManyRequests();
    }
}
