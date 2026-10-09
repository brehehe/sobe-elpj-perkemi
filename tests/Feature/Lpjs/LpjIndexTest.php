<?php

namespace Tests\Feature\Lpjs;

use App\Enums\LpjStatus;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class LpjIndexTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/lpjs')->assertRedirect('/login');
    }

    public function test_owner_can_search_filter_and_sort_lpjs_in_their_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'Unit Jawa Barat']);
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $matching = $this->completeWorkflow($unit, $owner);
        $otherLocal = $this->completeWorkflow($unit, $owner);
        $hidden = $this->completeWorkflow();

        $matching['activity']->update(['code' => 'ACT-SASARAN-01', 'title' => 'Kegiatan Sasaran']);
        $matching['lpj']->update(['status' => LpjStatus::ReadyToSubmit]);
        $otherLocal['activity']->update(['code' => 'ACT-LOKAL-02']);
        $otherLocal['lpj']->update(['status' => LpjStatus::InVerification]);
        $hidden['activity']->update(['code' => 'ACT-RAHASIA-03']);
        $hidden['lpj']->update(['status' => LpjStatus::ReadyToSubmit]);

        $query = http_build_query([
            'q' => 'SASARAN',
            'status' => LpjStatus::ReadyToSubmit->value,
            'sort' => 'oldest',
        ]);

        $this->actingAs($owner)->get('/lpjs?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lpjs/index')
                ->where('scope.can_view_all_units', false)
                ->where('scope.label', 'Unit Jawa Barat')
                ->where('filters.q', 'SASARAN')
                ->where('filters.status', LpjStatus::ReadyToSubmit->value)
                ->where('filters.sort', 'oldest')
                ->where('summary.total', 2)
                ->where('summary.draft', 1)
                ->where('summary.in_process', 1)
                ->where('summary.finalized', 0)
                ->where('summary.filtered', 1)
                ->has('lpjs.data', 1)
                ->where('lpjs.data.0.id', $matching['lpj']->id)
                ->where('lpjs.data.0.activity.code', 'ACT-SASARAN-01')
                ->has('options.units', 0)
                ->has('options.statuses', count(LpjStatus::cases())));
    }

    public function test_pb_verifier_can_scope_the_index_to_another_unit(): void
    {
        $homeUnit = Unit::factory()->create(['name' => 'Pengurus Besar']);
        $targetUnit = Unit::factory()->create(['name' => 'Unit Sasaran']);
        $pbVerifier = $this->userWithRole('PB Verifier', $homeUnit);
        $this->completeWorkflow($homeUnit);
        $target = $this->completeWorkflow($targetUnit);

        $this->actingAs($pbVerifier)->get('/lpjs?unit='.$targetUnit->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_all_units', true)
                ->where('scope.label', 'Unit Sasaran')
                ->where('filters.unit', $targetUnit->id)
                ->where('summary.total', 1)
                ->where('summary.filtered', 1)
                ->has('lpjs.data', 1)
                ->where('lpjs.data.0.id', $target['lpj']->id)
                ->where('lpjs.data.0.activity.unit.id', $targetUnit->id)
                ->has('options.units', 2));
    }

    public function test_local_user_cannot_probe_another_unit_filter(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);

        $this->actingAs($owner)
            ->get('/lpjs?unit='.$otherUnit->id)
            ->assertNotFound();
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);

        $this->actingAs($owner)
            ->from('/lpjs')
            ->get('/lpjs?status=NOT_A_REAL_STATUS')
            ->assertRedirect('/lpjs')
            ->assertSessionHasErrors('status');
    }
}
