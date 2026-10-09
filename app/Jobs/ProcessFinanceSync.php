<?php

namespace App\Jobs;

use App\Actions\MarkIntegrationRunFailureAction;
use App\Actions\SyncFinancialTransactionAction;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ProcessFinanceSync implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload) {}

    /**
     * Execute the job.
     */
    public function handle(SyncFinancialTransactionAction $sync, MarkIntegrationRunFailureAction $markFailure): void
    {
        $actor = isset($this->payload['triggered_by']) ? User::query()->find($this->payload['triggered_by']) : null;

        try {
            $sync->handle($this->payload, $actor);
        } catch (Throwable $exception) {
            $terminal = ! $markFailure->isRetryable($exception);
            $markFailure->handle($this->payload, $exception, max(1, $this->attempts()), $terminal, $actor);

            if ($terminal) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $actor = isset($this->payload['triggered_by']) ? User::query()->find($this->payload['triggered_by']) : null;
        app(MarkIntegrationRunFailureAction::class)->handle(
            $this->payload,
            $exception ?? new RuntimeException('Integration job failed without exception context.'),
            $this->tries,
            true,
            $actor,
        );
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return config('integrations.retry_delays', [30, 120, 600, 1800]);
    }

    public function uniqueId(): string
    {
        return (string) $this->payload['idempotency_key'];
    }
}
