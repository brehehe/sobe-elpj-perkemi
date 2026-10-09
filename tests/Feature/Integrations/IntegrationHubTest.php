<?php

namespace Tests\Feature\Integrations;

use App\Actions\SyncFinancialTransactionAction;
use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Jobs\ProcessFinanceSync;
use App\Models\IntegrationRun;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class IntegrationHubTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_hub_derives_provider_health_and_metrics_from_actual_runs(): void
    {
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        app(SyncFinancialTransactionAction::class)->handle($this->payload($workflow['activity']->id), $finance);

        $this->actingAs($finance)->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('integrations/index')
                ->where('summary.total_runs', 1)
                ->where('summary.succeeded_runs', 1)
                ->has('providers', 8)
                ->where('providers.0.health', 'NOT_OBSERVED')
                ->where('providers.0.observed', false)
                ->where('providers.6.key', 'SIMKEU')
                ->where('providers.6.health', 'OPERATIONAL')
                ->where('providers.6.observed', true)
                ->where('providers.6.records_succeeded', 1)
                ->where('providers.6.owner.name', $finance->name)
                ->where('runs.data.0.contract_version', 'finance-transaction-v1'));
    }

    public function test_non_global_user_only_sees_runs_from_their_unit(): void
    {
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        IntegrationRun::factory()->for($workflow['unit'])->create([
            'owner_id' => $finance->id,
            'status' => IntegrationStatus::Succeeded,
            'retry_status' => IntegrationRetryStatus::NotRequired,
            'completed_at' => now(),
        ]);
        IntegrationRun::factory()->for(Unit::factory())->create([
            'status' => IntegrationStatus::DeadLetter,
            'retry_status' => IntegrationRetryStatus::Exhausted,
        ]);

        $this->actingAs($finance)->get('/integrations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_runs', 1)
                ->has('runs.data', 1));
    }

    public function test_authorized_owner_can_queue_a_dead_letter_retry(): void
    {
        Queue::fake([ProcessFinanceSync::class]);
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        $payload = $this->payload($workflow['activity']->id);
        $run = IntegrationRun::factory()->for($workflow['unit'])->create([
            'owner_id' => $finance->id,
            'provider' => 'SIMKEU',
            'idempotency_key' => $payload['idempotency_key'],
            'status' => IntegrationStatus::DeadLetter,
            'retry_status' => IntegrationRetryStatus::Exhausted,
            'request_metadata' => [
                'activity_id' => $workflow['activity']->id,
                'external_id' => $payload['external_id'],
                'payload' => $payload,
            ],
        ]);

        $this->actingAs($finance)
            ->post("/integration-runs/{$run->id}/retries")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $run->refresh();
        $this->assertSame(IntegrationStatus::Pending, $run->status);
        $this->assertSame(IntegrationRetryStatus::Queued, $run->retry_status);
        $this->assertSame($finance->id, $run->triggered_by);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $run->id,
            'event_type' => 'integration.retry_queued',
            'to_status' => IntegrationStatus::Pending->value,
        ]);
        Queue::assertPushed(ProcessFinanceSync::class, fn (ProcessFinanceSync $job): bool => $job->payload['idempotency_key'] === $run->idempotency_key);
    }

    public function test_other_unit_cannot_discover_retryable_run(): void
    {
        Queue::fake([ProcessFinanceSync::class]);
        $workflow = $this->completeWorkflow();
        $otherFinance = $this->userWithRole('Finance PIC', Unit::factory()->create());
        $payload = $this->payload($workflow['activity']->id);
        $run = IntegrationRun::factory()->for($workflow['unit'])->create([
            'status' => IntegrationStatus::DeadLetter,
            'retry_status' => IntegrationRetryStatus::Exhausted,
            'request_metadata' => ['payload' => $payload],
        ]);

        $this->actingAs($otherFinance)
            ->post("/integration-runs/{$run->id}/retries")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_user_without_integration_permission_cannot_open_hub(): void
    {
        $workflow = $this->completeWorkflow();
        $verifier = $this->userWithRole('Verifier Admin', $workflow['unit']);

        $this->actingAs($verifier)->get('/integrations')->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(string $activityId): array
    {
        return [
            'provider' => 'SIMKEU',
            'idempotency_key' => 'hub-sync-001',
            'activity_id' => $activityId,
            'external_id' => 'hub-ledger-001',
            'reference_number' => 'BANK-HUB-001',
            'description' => 'Sinkronisasi untuk monitoring hub',
            'transaction_date' => now()->toDateString(),
            'amount' => 125_000,
            'currency' => 'IDR',
        ];
    }
}
