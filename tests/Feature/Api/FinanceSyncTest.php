<?php

namespace Tests\Feature\Api;

use App\Models\AuditEvent;
use App\Models\FinancialTransaction;
use App\Models\IntegrationRun;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class FinanceSyncTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_retry_with_same_idempotency_key_does_not_duplicate_transaction(): void
    {
        $workflow = $this->completeWorkflow();
        $administrator = $this->userWithRole('Administrator', $workflow['unit']);
        Sanctum::actingAs($administrator);
        $payload = [
            'provider' => 'SIMKEU',
            'idempotency_key' => 'sync-kgt-001',
            'activity_id' => $workflow['activity']->id,
            'external_id' => 'ledger-0001',
            'reference_number' => 'BANK-9988',
            'description' => 'Pembayaran tempat kegiatan',
            'transaction_date' => now()->toDateString(),
            'amount' => 2_500_000,
            'currency' => 'IDR',
        ];

        $this->postJson('/api/v1/integrations/finance/transactions', $payload)->assertOk();
        $this->postJson('/api/v1/integrations/finance/transactions', $payload)->assertOk();

        $this->assertSame(1, FinancialTransaction::query()->where('source', 'SIMKEU')->where('external_id', 'ledger-0001')->count());
        $this->assertSame(1, IntegrationRun::query()->where('idempotency_key', 'sync-kgt-001')->count());
    }

    public function test_successful_sync_records_operational_metrics_owner_and_audit_lineage(): void
    {
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        Sanctum::actingAs($finance);

        $this->postJson('/api/v1/integrations/finance/transactions', [
            'provider' => 'SIMKEU',
            'idempotency_key' => 'sync-observability-001',
            'activity_id' => $workflow['activity']->id,
            'external_id' => 'ledger-observability-001',
            'reference_number' => 'BANK-OBS-001',
            'description' => 'Pembayaran yang dapat dipantau',
            'transaction_date' => now()->toDateString(),
            'amount' => 150_000,
            'currency' => 'IDR',
        ])->assertOk();

        $run = IntegrationRun::query()->where('idempotency_key', 'sync-observability-001')->firstOrFail();
        $this->assertSame($workflow['unit']->id, $run->unit_id);
        $this->assertSame($finance->id, $run->owner_id);
        $this->assertSame($finance->id, $run->triggered_by);
        $this->assertSame('finance-transaction-v1', $run->contract_version);
        $this->assertSame(1, $run->records_received);
        $this->assertSame(1, $run->records_succeeded);
        $this->assertSame(0, $run->records_failed);
        $this->assertNotNull($run->last_attempted_at);
        $this->assertNotNull($run->correlation_id);
        $this->assertTrue(AuditEvent::query()->where('auditable_id', $run->id)->where('event_type', 'integration.sync_succeeded')->exists());
    }

    public function test_reusing_idempotency_key_for_different_payload_returns_conflict(): void
    {
        $workflow = $this->completeWorkflow();
        $administrator = $this->userWithRole('Administrator', $workflow['unit']);
        Sanctum::actingAs($administrator);
        $payload = [
            'provider' => 'SIMKEU', 'idempotency_key' => 'sync-conflict', 'activity_id' => $workflow['activity']->id,
            'external_id' => 'ledger-1001', 'description' => 'Transaksi pertama', 'transaction_date' => now()->toDateString(),
            'amount' => 100_000, 'currency' => 'IDR',
        ];

        $this->postJson('/api/v1/integrations/finance/transactions', $payload)->assertOk();
        $this->postJson('/api/v1/integrations/finance/transactions', [...$payload, 'amount' => 200_000])->assertConflict();
    }

    public function test_non_global_integrator_cannot_sync_transaction_to_another_unit(): void
    {
        $first = $this->completeWorkflow();
        $second = $this->completeWorkflow(Unit::factory()->create());
        $administrator = $this->userWithRole('Administrator', $first['unit']);
        Sanctum::actingAs($administrator);

        $this->postJson('/api/v1/integrations/finance/transactions', [
            'provider' => 'SIMKEU', 'idempotency_key' => 'cross-unit', 'activity_id' => $second['activity']->id,
            'external_id' => 'ledger-cross', 'description' => 'Tidak diizinkan', 'transaction_date' => now()->toDateString(),
            'amount' => 100_000, 'currency' => 'IDR',
        ])->assertUnprocessable()->assertJsonValidationErrors('activity_id');
    }
}
