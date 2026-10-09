<?php

namespace App\Actions;

use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Jobs\ProcessFinanceSync;
use App\Models\IntegrationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QueueIntegrationRetryAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(IntegrationRun $integrationRun, User $user): IntegrationRun
    {
        return DB::transaction(function () use ($integrationRun, $user): IntegrationRun {
            $run = IntegrationRun::query()->lockForUpdate()->findOrFail($integrationRun->id);

            if (! in_array($run->status, [IntegrationStatus::Failed, IntegrationStatus::DeadLetter], true)) {
                throw ValidationException::withMessages([
                    'retry' => 'Hanya run FAILED atau DEAD_LETTER yang dapat diantrekan ulang.',
                ]);
            }

            $payload = data_get($run->request_metadata, 'payload');
            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'retry' => 'Payload replay tidak tersedia untuk run ini. Kirim ulang dari source system dengan idempotency key baru.',
                ]);
            }

            $fromStatus = $run->status->value;
            $correlationId = (string) Str::uuid7();
            $run->update([
                'status' => IntegrationStatus::Pending,
                'retry_status' => IntegrationRetryStatus::Queued,
                'triggered_by' => $user->id,
                'correlation_id' => $correlationId,
                'next_retry_at' => now(),
                'completed_at' => null,
            ]);

            $this->recordAuditEvent->handle($run, 'integration.retry_queued', $user, $fromStatus, IntegrationStatus::Pending->value, [
                'provider' => $run->provider,
                'idempotency_key' => $run->idempotency_key,
                'previous_attempts' => $run->attempts,
            ]);

            DB::afterCommit(fn () => ProcessFinanceSync::dispatch([
                ...$payload,
                'triggered_by' => $user->id,
                'correlation_id' => $correlationId,
            ])->onQueue('integrations'));

            return $run;
        }, attempts: 3);
    }
}
