<?php

namespace Tests\Feature\Audit;

use App\Actions\RecordAuditEventAction;
use App\Models\Activity;
use App\Models\AuditEvent;
use App\Models\Evidence;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/audit')->assertRedirect('/login');
    }

    public function test_user_without_audit_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();
        $owner = $this->userWithRole('Owner/PIC', $unit);

        $this->actingAs($owner)->get('/audit')->assertForbidden();
    }

    public function test_auditor_only_sees_events_from_their_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'SOBE Jawa Barat']);
        $otherUnit = Unit::factory()->create(['name' => 'SOBE Jawa Timur']);
        $auditor = $this->userWithRole('Auditor', $unit);
        $visibleActor = User::factory()->forUnit($unit)->create(['name' => 'Actor Terlihat']);
        $hiddenActor = User::factory()->forUnit($otherUnit)->create(['name' => 'Actor Tersembunyi']);
        $visible = $this->event($unit, $visibleActor, ['event_type' => 'lpj.submitted']);
        $this->event($otherUnit, $hiddenActor, ['event_type' => 'finding.opened']);

        $this->actingAs($auditor)->get('/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/index')
                ->where('scope.can_view_all_units', false)
                ->where('scope.label', 'SOBE Jawa Barat')
                ->where('summary.total', 1)
                ->has('events.data', 1)
                ->where('events.data.0.id', $visible->id)
                ->where('events.data.0.actor.name', 'Actor Terlihat')
                ->has('options.units', 0));
    }

    public function test_super_admin_can_filter_a_specific_unit(): void
    {
        $firstUnit = Unit::factory()->create(['name' => 'Unit Pertama']);
        $secondUnit = Unit::factory()->create(['name' => 'Unit Kedua']);
        $admin = $this->userWithRole('Super Admin', $firstUnit);
        $firstActor = User::factory()->forUnit($firstUnit)->create();
        $secondActor = User::factory()->forUnit($secondUnit)->create();
        $this->event($firstUnit, $firstActor, ['event_type' => 'plan.submitted']);
        $visible = $this->event($secondUnit, $secondActor, ['event_type' => 'budget.reviewed']);

        $this->actingAs($admin)->get('/audit?unit='.$secondUnit->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.can_view_all_units', true)
                ->where('scope.label', 'Unit Kedua')
                ->where('filters.unit', $secondUnit->id)
                ->where('summary.total', 1)
                ->has('events.data', 1)
                ->where('events.data.0.id', $visible->id)
                ->has('options.units', 2));
    }

    public function test_local_auditor_cannot_probe_another_unit_filter(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $auditor = $this->userWithRole('Auditor', $unit);

        $this->actingAs($auditor)
            ->get('/audit?unit='.$otherUnit->id)
            ->assertNotFound();
    }

    public function test_server_filters_apply_to_actor_domain_event_object_date_and_result(): void
    {
        $unit = Unit::factory()->create();
        $auditor = $this->userWithRole('Auditor', $unit);
        $targetActor = User::factory()->forUnit($unit)->create(['name' => 'Rani Pemeriksa']);
        $otherActor = User::factory()->forUnit($unit)->create(['name' => 'Actor Lain']);
        $matching = $this->event($unit, $targetActor, [
            'auditable_id' => '01a11b18-3e9e-7145-aa16-d97b63dc6a2e',
            'event_type' => 'lpj.approval_recorded',
            'source_channel' => 'API',
            'result' => 'SUCCEEDED',
            'created_at' => '2026-10-05 10:00:00',
        ]);
        $this->event($unit, $otherActor, [
            'auditable_id' => '01a11b18-3e9e-7145-aa16-d97b63dc6a3f',
            'event_type' => 'lpj.submitted',
            'source_channel' => 'WEB',
            'result' => 'FAILED',
            'created_at' => '2026-10-01 10:00:00',
        ]);

        $query = http_build_query([
            'q' => 'APPROVAL',
            'object' => '6a2e',
            'domain' => 'lpj',
            'event' => 'lpj.approval_recorded',
            'actor' => $targetActor->id,
            'date_from' => '2026-10-05',
            'date_to' => '2026-10-05',
            'result' => 'SUCCEEDED',
            'channel' => 'API',
            'sort' => 'oldest',
        ]);

        $this->actingAs($auditor)->get('/audit?'.$query)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'APPROVAL')
                ->where('filters.object', '6a2e')
                ->where('filters.domain', 'lpj')
                ->where('filters.actor', $targetActor->id)
                ->where('filters.sort', 'oldest')
                ->where('summary.total', 1)
                ->has('events.data', 1)
                ->where('events.data.0.id', $matching->id));
    }

    public function test_invalid_date_range_and_sort_have_user_visible_errors(): void
    {
        $unit = Unit::factory()->create();
        $auditor = $this->userWithRole('Auditor', $unit);

        $this->actingAs($auditor)
            ->from('/audit')
            ->get('/audit?date_from=2026-10-10&date_to=2026-10-01&sort=drop-table')
            ->assertRedirect('/audit')
            ->assertSessionHasErrors([
                'date_to' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
                'sort' => 'Urutan audit tidak dikenali.',
            ]);
    }

    public function test_event_payload_is_sanitized_and_includes_traceable_context(): void
    {
        $unit = Unit::factory()->create(['code' => 'AUDIT-UNIT']);
        $auditor = $this->userWithRole('Auditor', $unit);
        $activity = Activity::factory()->for($unit)->create(['code' => 'KGT-AUDIT-001']);
        $event = $this->event($unit, $auditor, [
            'auditable_type' => $activity->getMorphClass(),
            'auditable_id' => $activity->id,
            'event_type' => 'activity.started',
            'request_id' => 'req-audit-context',
            'object_version' => '4',
            'authority' => 'activities.manage',
            'payload_version' => 2,
            'from_status' => 'PLANNED',
            'to_status' => 'IN_PROGRESS',
            'evidence_references' => ['EV-001'],
            'ip_address' => '192.168.10.44',
            'user_agent' => str_repeat('Browser Audit ', 20),
            'payload' => [
                'notes' => 'Kegiatan dimulai sesuai mandat.',
                'evidence_id' => 'EV-001',
                'api_token' => 'super-secret-token',
                'nested' => ['password' => 'never-render-this'],
            ],
        ]);

        $this->actingAs($auditor)->get('/audit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data.0.id', $event->id)
                ->where('events.data.0.object.label', 'Kegiatan')
                ->where('events.data.0.object.url', '/activities/'.$activity->id)
                ->where('events.data.0.object.version', '4')
                ->where('events.data.0.change.before', 'PLANNED')
                ->where('events.data.0.change.after', 'IN_PROGRESS')
                ->where('events.data.0.request_id', 'req-audit-context')
                ->where('events.data.0.authority', 'activities.manage')
                ->where('events.data.0.payload_version', 2)
                ->where('events.data.0.evidence_references', ['EV-001'])
                ->where('events.data.0.reason', 'Kegiatan dimulai sesuai mandat.')
                ->where('events.data.0.location.ip_address', '192.168.10.*')
                ->where('events.data.0.payload.api_token', '[DISAMARKAN]')
                ->where('events.data.0.payload.nested.password', '[DISAMARKAN]')
                ->where('events.data.0.payload.evidence_id', 'EV-001')
                ->where('events.data.0.event_id', $event->event_id)
                ->where('events.data.0.correlation_id', $event->correlation_id));
    }

    public function test_recording_action_captures_authority_version_and_evidence_lineage(): void
    {
        $unit = Unit::factory()->create();
        $auditor = $this->userWithRole('Auditor', $unit);
        $evidence = Evidence::factory()->create(['version' => 3]);

        $event = app(RecordAuditEventAction::class)->handle(
            $evidence,
            'evidence.reviewed',
            $auditor,
            payload: ['supporting_evidence_ids' => ['EV-EXTERNAL-1']],
        );

        $this->assertNotNull($event->event_id);
        $this->assertNotNull($event->correlation_id);
        $this->assertNotNull($event->request_id);
        $this->assertSame('3', $event->object_version);
        $this->assertSame('Auditor', $event->authority);
        $this->assertSame(1, $event->payload_version);
        $this->assertSame([$evidence->id, 'EV-EXTERNAL-1'], $event->evidence_references);
    }

    public function test_auditor_can_export_only_scoped_sanitized_events_and_export_is_audited(): void
    {
        $this->travelTo('2026-10-08 14:30:00');
        $unit = Unit::factory()->create(['name' => 'Unit Audit']);
        $otherUnit = Unit::factory()->create(['name' => 'Unit Rahasia']);
        $auditor = $this->userWithRole('Auditor', $unit);
        $formulaActor = User::factory()->forUnit($unit)->create(['name' => '=WEBSERVICE("https://invalid.test")']);
        $otherActor = User::factory()->forUnit($otherUnit)->create();
        $this->event($unit, $formulaActor, [
            'event_type' => 'finding.opened',
            'payload' => ['notes' => 'Perlu CAPA', 'secret' => 'tidak-boleh-ekspor'],
        ]);
        $this->event($otherUnit, $otherActor, ['event_type' => 'lpj.archived']);

        $response = $this->actingAs($auditor)->get('/audit/export?domain=finding', [
            'X-Correlation-ID' => '01J9ZC4S0QR2KHJ8Y0AB2D4E6F',
            'X-Request-ID' => 'request-audit-export',
        ]);

        $response->assertOk()->assertDownload('audit-trail-2026-10-08.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString('finding.opened', $content);
        $this->assertStringContainsString("'=WEBSERVICE", $content);
        $this->assertStringContainsString('[DISAMARKAN]', $content);
        $this->assertStringNotContainsString('tidak-boleh-ekspor', $content);
        $this->assertStringNotContainsString('lpj.archived', $content);
        $this->assertDatabaseHas('audit_events', [
            'unit_id' => $unit->id,
            'actor_id' => $auditor->id,
            'event_type' => 'audit.exported',
            'request_id' => 'request-audit-export',
            'authority' => 'audit.view',
            'payload_version' => 1,
        ]);
    }

    private function event(Unit $unit, User $actor, array $attributes = []): AuditEvent
    {
        return AuditEvent::factory()->create([
            'unit_id' => $unit->id,
            'actor_id' => $actor->id,
            'event_id' => (string) Str::uuid7(),
            'correlation_id' => (string) Str::uuid7(),
            'auditable_type' => User::class,
            'auditable_id' => $actor->id,
            'source_channel' => 'WEB',
            'result' => 'SUCCEEDED',
            ...$attributes,
        ]);
    }
}
