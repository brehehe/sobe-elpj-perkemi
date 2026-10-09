<?php

namespace Tests\Feature\Jobs;

use App\Actions\MarkIntegrationRunFailureAction;
use App\Actions\SyncFinancialTransactionAction;
use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Jobs\ProcessFinanceSync;
use App\Models\IntegrationRun;
use App\Models\SmartTask;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\BuildsWorkflow;
use Tests\TestCase;

class ProcessFinanceSyncTest extends TestCase
{
    use BuildsWorkflow, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_retryable_failure_is_scheduled_then_dead_lettered_with_owner_task_and_notification(): void
    {
        Notification::fake([WorkflowTaskAssigned::class]);
        $workflow = $this->completeWorkflow();
        $finance = $this->userWithRole('Finance PIC', $workflow['unit']);
        $payload = [
            'provider' => 'SIMKEU',
            'idempotency_key' => 'job-failure-001',
            'activity_id' => $workflow['activity']->id,
            'external_id' => 'job-ledger-001',
            'description' => 'Transaksi yang gagal sementara',
            'transaction_date' => now()->toDateString(),
            'amount' => 175_000,
            'currency' => 'IDR',
            'triggered_by' => $finance->id,
        ];
        $exception = new RuntimeException('Temporary upstream failure.');
        $sync = $this->mock(SyncFinancialTransactionAction::class);
        $sync->shouldReceive('handle')->once()->andThrow($exception);
        $job = new ProcessFinanceSync($payload);

        try {
            $job->handle($sync, app(MarkIntegrationRunFailureAction::class));
            $this->fail('Retryable integration failure should be rethrown to the queue worker.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $run = IntegrationRun::query()->where('idempotency_key', 'job-failure-001')->firstOrFail();
        $this->assertSame(IntegrationStatus::Failed, $run->status);
        $this->assertSame(IntegrationRetryStatus::Scheduled, $run->retry_status);
        $this->assertNotNull($run->next_retry_at);
        $this->assertSame('UNKNOWN', $run->error_category);

        $job->failed($exception);
        $run->refresh();
        $task = SmartTask::query()->where('metadata->integration_run_id', $run->id)->firstOrFail();

        $this->assertSame(IntegrationStatus::DeadLetter, $run->status);
        $this->assertSame(IntegrationRetryStatus::Exhausted, $run->retry_status);
        $this->assertSame($finance->id, $task->assignee_id);
        $this->assertSame('INTEGRATION_FAILURE', $task->type);
        $this->assertDatabaseHas('audit_events', [
            'auditable_id' => $run->id,
            'event_type' => 'integration.sync_dead_lettered',
            'result' => 'FAILED',
        ]);
        Notification::assertSentTo($finance, WorkflowTaskAssigned::class);
    }
}
