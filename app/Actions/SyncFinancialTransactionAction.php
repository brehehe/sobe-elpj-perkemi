<?php

namespace App\Actions;

use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Models\Activity;
use App\Models\FinancialTransaction;
use App\Models\IntegrationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SyncFinancialTransactionAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private ResolveIntegrationOwnerAction $resolveIntegrationOwner,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, ?User $actor = null): FinancialTransaction
    {
        $startedAt = hrtime(true);
        $payload = $this->businessPayload($data);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $activity = Activity::query()->findOrFail($data['activity_id']);
        $owner = $this->resolveIntegrationOwner->handle($activity->unit_id, $actor);
        $correlationId = $data['correlation_id'] ?? request()?->header('X-Correlation-ID') ?? (string) Str::uuid7();

        $run = DB::transaction(function () use ($actor, $activity, $correlationId, $data, $owner, $payload, $payloadHash): IntegrationRun {
            $run = IntegrationRun::query()->lockForUpdate()->firstOrCreate(
                ['idempotency_key' => $data['idempotency_key']],
                [
                    'unit_id' => $activity->unit_id,
                    'owner_id' => $owner?->id,
                    'triggered_by' => $actor?->id,
                    'provider' => $data['provider'],
                    'contract_version' => $this->contractVersion($data['provider']),
                    'correlation_id' => $correlationId,
                    'status' => IntegrationStatus::Processing,
                    'attempts' => 1,
                    'records_received' => 1,
                    'payload_hash' => $payloadHash,
                    'request_metadata' => [
                        'activity_id' => $data['activity_id'],
                        'external_id' => $data['external_id'],
                        'payload' => $payload,
                    ],
                    'retry_status' => IntegrationRetryStatus::NotRequired,
                    'last_attempted_at' => now(),
                ],
            );

            if ($run->payload_hash !== $payloadHash) {
                throw new ConflictHttpException('Idempotency key has already been used for a different payload.');
            }

            if (! $run->wasRecentlyCreated) {
                $run->update([
                    'unit_id' => $run->unit_id ?? $activity->unit_id,
                    'owner_id' => $run->owner_id ?? $owner?->id,
                    'triggered_by' => $actor?->id,
                    'correlation_id' => $correlationId,
                    'attempts' => $run->attempts + 1,
                    'status' => $run->status === IntegrationStatus::Succeeded ? IntegrationStatus::Succeeded : IntegrationStatus::Processing,
                    'retry_status' => $run->status === IntegrationStatus::Succeeded ? IntegrationRetryStatus::NotRequired : IntegrationRetryStatus::Retrying,
                    'last_attempted_at' => now(),
                ]);
            }

            return $run;
        }, attempts: 3);

        if ($run->status === IntegrationStatus::Succeeded) {
            $transaction = FinancialTransaction::query()->find(data_get($run->response_metadata, 'transaction_id'));

            if ($transaction !== null) {
                $this->recordAuditEvent->handle($run, 'integration.sync_replayed', $actor, IntegrationStatus::Succeeded->value, IntegrationStatus::Succeeded->value, [
                    'provider' => $run->provider,
                    'idempotency_key' => $run->idempotency_key,
                ]);

                return $transaction;
            }
        }

        return DB::transaction(function () use ($actor, $data, $payloadHash, $run, $startedAt): FinancialTransaction {
            $transaction = FinancialTransaction::query()->updateOrCreate(
                ['source' => $data['provider'], 'external_id' => $data['external_id']],
                [
                    'activity_id' => $data['activity_id'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'account_code' => $data['account_code'] ?? null,
                    'type' => 'EXPENSE',
                    'description' => $data['description'],
                    'transaction_date' => $data['transaction_date'],
                    'amount' => $data['amount'],
                    'currency' => strtoupper($data['currency'] ?? 'IDR'),
                    'status' => 'POSTED',
                    'source_payload' => ['payload_hash' => $payloadHash],
                ],
            );

            $run->update([
                'status' => IntegrationStatus::Succeeded,
                'retry_status' => IntegrationRetryStatus::NotRequired,
                'records_succeeded' => 1,
                'records_failed' => 0,
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'response_metadata' => ['transaction_id' => $transaction->id],
                'completed_at' => now(),
                'last_error' => null,
                'error_category' => null,
                'next_retry_at' => null,
            ]);

            $this->recordAuditEvent->handle($run, 'integration.sync_succeeded', $actor, IntegrationStatus::Processing->value, IntegrationStatus::Succeeded->value, [
                'provider' => $run->provider,
                'idempotency_key' => $run->idempotency_key,
                'transaction_id' => $transaction->id,
                'contract_version' => $run->contract_version,
                'duration_ms' => $run->duration_ms,
            ]);

            return $transaction;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function businessPayload(array $data): array
    {
        unset($data['triggered_by'], $data['correlation_id']);
        ksort($data);

        return $data;
    }

    private function contractVersion(string $provider): string
    {
        return config("integrations.providers.{$provider}.contract_version", config('integrations.default_contract_version'));
    }
}
