<?php

namespace Tests\Feature\Planning;

use App\Models\Mandate;
use App\Models\Plan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class PlanningWorkflowTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_planning_is_versioned_and_reviewed_by_a_different_user(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $reviewer = $this->userWithRole('Approver', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $owner->id, 'status' => 'ACTIVE']);
        $payload = [
            'mandate_id' => $mandate->id,
            'code' => 'REN-TEST-001',
            'activity_type' => 'PEMBINAAN',
            'objective' => 'Meningkatkan tata kelola organisasi.',
            'output' => 'Satu kegiatan pembinaan terlaksana.',
            'outcome' => 'Kepatuhan administrasi meningkat.',
            'target_participants' => 25,
            'kpis' => [
                ['name' => 'Kehadiran', 'target' => 90, 'unit' => 'persen'],
                ['name' => 'Kelulusan', 'target' => 20, 'unit' => 'orang'],
            ],
            'schedule' => ['starts_at' => '2026-11-10T08:00', 'ends_at' => '2026-11-12T16:00'],
            'resources' => ['Empat pelatih', 'Ruang tatami'],
            'risks' => [['risk' => 'Kehadiran rendah', 'mitigation' => 'Konfirmasi peserta H-7']],
        ];

        $this->actingAs($owner)->post('/planning', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan = Plan::query()->where('code', 'REN-TEST-001')->firstOrFail();
        $this->assertSame('SUBMITTED', $plan->status->value);
        $this->assertTrue($plan->currentVersionRecord->is_immutable);
        $this->assertCount(2, $plan->currentVersionRecord->kpis);
        $this->assertSame(['Empat pelatih', 'Ruang tatami'], $plan->currentVersionRecord->resources);
        $this->assertSame('Kehadiran rendah', $plan->currentVersionRecord->risks[0]['risk']);

        $this->actingAs($owner)->post("/planning/{$plan->id}/reviews", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($reviewer)->post("/planning/{$plan->id}/reviews", ['decision' => 'RETURNED', 'notes' => 'Tambahkan indikator hasil.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("/planning/{$plan->id}/versions", [...$payload, 'change_reason' => 'Menambahkan indikator hasil.'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $plan->fresh()->current_version);
        $this->assertCount(2, $plan->versions);
        $this->actingAs($reviewer)->post("/planning/{$plan->id}/reviews", ['decision' => 'APPROVED'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $plan->fresh()->status->value);
    }

    public function test_operational_workspaces_are_available_by_permission(): void
    {
        $workflow = $this->completeWorkflow();
        $this->actingAs($workflow['owner'])->get('/planning')->assertOk();
        $this->actingAs($workflow['owner'])->get('/budgets')->assertOk();
        $this->actingAs($workflow['owner'])->get('/finance')->assertOk();
        $this->actingAs($workflow['owner'])->get('/action-center')->assertOk();

        $auditor = $this->userWithRole('Auditor', $workflow['unit']);
        $this->actingAs($auditor)->get('/organization/hierarchy')->assertOk();
    }

    public function test_planning_rejects_unvalidated_nested_keys(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);
        $mandate = Mandate::factory()->create(['unit_id' => $unit->id, 'created_by' => $owner->id, 'status' => 'ACTIVE']);

        $response = $this->actingAs($owner)->post('/planning', [
            'mandate_id' => $mandate->id,
            'code' => 'REN-SECURE-001',
            'activity_type' => 'PEMBINAAN',
            'objective' => 'Menguji batas input planning.',
            'output' => 'Input tervalidasi.',
            'outcome' => 'Tidak ada key asing tersimpan.',
            'target_participants' => 10,
            'kpis' => [['name' => 'Kehadiran', 'target' => 90, 'unit' => 'persen', 'admin' => true]],
        ]);

        $response->assertSessionHasErrors('kpis.0');
        $this->assertDatabaseMissing('plans', ['code' => 'REN-SECURE-001']);
    }
}
