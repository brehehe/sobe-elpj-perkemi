<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MarkIntegrationRunFailureAction;
use App\Actions\RecordAuditEventAction;
use App\Actions\SyncFinancialTransactionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SyncFinancialTransaction;
use App\Jobs\ProcessFinanceSync;
use App\Models\IntegrationRun;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class FinanceSyncController extends Controller
{
    public function store(
        SyncFinancialTransaction $request,
        SyncFinancialTransactionAction $sync,
        MarkIntegrationRunFailureAction $markFailure,
        RecordAuditEventAction $recordAuditEvent,
    ): JsonResponse {
        $payload = $request->validated();

        try {
            return response()->json(['data' => $sync->handle($payload, $request->user())], 200);
        } catch (ConflictHttpException $exception) {
            $run = IntegrationRun::query()->where('idempotency_key', $payload['idempotency_key'])->first();
            if ($run !== null) {
                $recordAuditEvent->handle($run, 'integration.idempotency_conflict', $request->user(), $run->status->value, $run->status->value, [
                    'provider' => $payload['provider'],
                ], 'FAILED');
            }

            throw $exception;
        } catch (Throwable $exception) {
            $terminal = ! $markFailure->isRetryable($exception);
            $run = $markFailure->handle($payload, $exception, 1, $terminal, $request->user());

            if (! $terminal) {
                ProcessFinanceSync::dispatch([
                    ...$payload,
                    'triggered_by' => $request->user()->id,
                    'correlation_id' => $run->correlation_id,
                ])->delay($run->next_retry_at)->onQueue('integrations')->afterResponse();
            }

            throw $exception;
        }
    }
}
