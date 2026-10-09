<?php

namespace App\Actions;

use App\Enums\IntegrationErrorCategory;
use App\Enums\IntegrationRetryStatus;
use App\Enums\IntegrationStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\IntegrationRun;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class MarkIntegrationRunFailureAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private ResolveIntegrationOwnerAction $resolveIntegrationOwner,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload, Throwable $exception, int $attempt, bool $terminal, ?User $actor = null): IntegrationRun
    {
        $businessPayload = $payload;
        unset($businessPayload['triggered_by'], $businessPayload['correlation_id']);
        ksort($businessPayload);
        $payloadHash = hash('sha256', json_encode($businessPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $activity = isset($payload['activity_id']) ? Activity::query()->find($payload['activity_id']) : null;
        $existingRun = IntegrationRun::query()->where('idempotency_key', $payload['idempotency_key'])->first();
        $unitId = $existingRun?->unit_id ?? $activity?->unit_id;
        $owner = $existingRun?->owner ?? $this->resolveIntegrationOwner->handle($unitId, $actor);
        $category = $this->category($exception);

        if ($existingRun?->status === IntegrationStatus::DeadLetter && $terminal) {
            return $existingRun;
        }

        return DB::transaction(function () use ($actor, $attempt, $businessPayload, $category, $exception, $existingRun, $owner, $payload, $payloadHash, $terminal, $unitId): IntegrationRun {
            $fromStatus = $existingRun?->status?->value;
            $status = $terminal ? IntegrationStatus::DeadLetter : IntegrationStatus::Failed;
            $retryStatus = $terminal ? IntegrationRetryStatus::Exhausted : IntegrationRetryStatus::Scheduled;
            $nextRetryAt = $terminal ? null : now()->addSeconds($this->retryDelay($attempt));
            $run = IntegrationRun::query()->updateOrCreate(
                ['idempotency_key' => $payload['idempotency_key']],
                [
                    'unit_id' => $unitId,
                    'owner_id' => $owner?->id,
                    'triggered_by' => $actor?->id ?? ($payload['triggered_by'] ?? null),
                    'provider' => $payload['provider'],
                    'contract_version' => config("integrations.providers.{$payload['provider']}.contract_version", config('integrations.default_contract_version')),
                    'correlation_id' => $payload['correlation_id'] ?? $existingRun?->correlation_id ?? (string) Str::uuid7(),
                    'status' => $status,
                    'attempts' => max($existingRun?->attempts ?? 0, $attempt),
                    'records_received' => 1,
                    'records_succeeded' => 0,
                    'records_failed' => 1,
                    'payload_hash' => $payloadHash,
                    'request_metadata' => [
                        'activity_id' => $payload['activity_id'] ?? null,
                        'external_id' => $payload['external_id'] ?? null,
                        'payload' => $businessPayload,
                    ],
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'error_category' => $category->value,
                    'retry_status' => $retryStatus,
                    'next_retry_at' => $nextRetryAt,
                    'last_attempted_at' => now(),
                    'completed_at' => $terminal ? now() : null,
                ],
            );

            if ($terminal && $unitId !== null) {
                $task = SmartTask::query()
                    ->where('metadata->integration_run_id', $run->id)
                    ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                    ->first();

                if ($task === null) {
                    $task = SmartTask::create([
                        'unit_id' => $unitId,
                        'activity_id' => $payload['activity_id'] ?? null,
                        'assignee_id' => $owner?->id,
                        'type' => 'INTEGRATION_FAILURE',
                        'title' => 'Pulihkan sinkronisasi '.$run->provider,
                        'description' => 'Run '.$run->idempotency_key.' masuk dead-letter. Periksa mapping atau sumber data, lalu jalankan retry terkontrol.',
                        'priority' => 'CRITICAL',
                        'status' => TaskStatus::Open,
                        'source' => 'INTEGRATION',
                        'due_at' => now()->addDay(),
                        'metadata' => ['integration_run_id' => $run->id, 'error_category' => $category->value],
                    ]);
                    DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
                }
            }

            $this->recordAuditEvent->handle(
                $run,
                $terminal ? 'integration.sync_dead_lettered' : 'integration.sync_failed',
                $actor,
                $fromStatus,
                $status->value,
                [
                    'provider' => $run->provider,
                    'attempt' => $run->attempts,
                    'error_category' => $category->value,
                    'retry_status' => $retryStatus->value,
                    'next_retry_at' => $nextRetryAt?->toIso8601String(),
                ],
                'FAILED',
            );

            return $run;
        }, attempts: 3);
    }

    public function isRetryable(Throwable $exception): bool
    {
        return ! ($exception instanceof ValidationException
            || $exception instanceof ModelNotFoundException
            || $exception instanceof AuthorizationException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500));
    }

    private function category(Throwable $exception): IntegrationErrorCategory
    {
        return match (true) {
            $exception instanceof ValidationException => IntegrationErrorCategory::Validation,
            $exception instanceof ModelNotFoundException => IntegrationErrorCategory::Mapping,
            $exception instanceof AuthorizationException => IntegrationErrorCategory::Authorization,
            $exception instanceof QueryException => IntegrationErrorCategory::Transient,
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500 => IntegrationErrorCategory::Permanent,
            default => IntegrationErrorCategory::Unknown,
        };
    }

    private function retryDelay(int $attempt): int
    {
        $delays = config('integrations.retry_delays', [30, 120, 600, 1800]);

        return $delays[min(max($attempt - 1, 0), count($delays) - 1)];
    }
}
