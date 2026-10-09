<?php

namespace App\Actions;

use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\TaskStatus;
use App\Models\FinancialTransaction;
use App\Models\ReconciliationException;
use App\Models\SmartTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveReconciliationExceptionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(ReconciliationException $exception, User $user, array $data): ReconciliationException
    {
        return DB::transaction(function () use ($exception, $user, $data): ReconciliationException {
            $transactionId = $exception->reconciliation()->value('financial_transaction_id');
            FinancialTransaction::query()->lockForUpdate()->findOrFail($transactionId);
            $lockedException = ReconciliationException::query()
                ->with('reconciliation.transaction')
                ->lockForUpdate()
                ->findOrFail($exception->id);
            if (! in_array($lockedException->status, [ReconciliationExceptionStatus::Open, ReconciliationExceptionStatus::InProgress, ReconciliationExceptionStatus::Rejected], true)) {
                throw ValidationException::withMessages(['status' => 'Exception ini sudah memiliki keputusan final.']);
            }

            $approvedException = $data['resolution_type'] === 'APPROVED_EXCEPTION';
            $toStatus = $approvedException
                ? ReconciliationExceptionStatus::PendingApproval
                : ReconciliationExceptionStatus::Resolved;
            $fromStatus = $lockedException->status->value;
            $lockedException->update([
                'resolved_by' => $approvedException ? null : $user->id,
                'requested_by' => $approvedException ? $user->id : null,
                'decided_by' => null,
                'status' => $toStatus,
                'resolution_type' => $data['resolution_type'],
                'resolution' => $data['resolution'],
                'is_approved_exception' => false,
                'decision' => null,
                'authority_policy_version' => $approvedException ? config('lpj.reconciliation_exception.authority_policy.version') : null,
                'decision_notes' => null,
                'requested_at' => $approvedException ? now() : null,
                'decided_at' => null,
                'resolved_at' => $approvedException ? null : now(),
            ]);

            if (! $approvedException) {
                $lockedException->reconciliation->update(['status' => ReconciliationStatus::Resolved]);
                $lockedException->reconciliation->transaction->update(['reconciliation_status' => ReconciliationStatus::Resolved]);
                SmartTask::query()
                    ->where('metadata->reconciliation_exception_id', $lockedException->id)
                    ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
                    ->update(['status' => TaskStatus::Done]);
            }

            $this->recordAuditEvent->handle($lockedException, $approvedException ? 'finance.reconciliation_exception_approval_requested' : 'finance.reconciliation_exception_resolved', $user, $fromStatus, $toStatus->value, [
                'resolution_type' => $data['resolution_type'],
                'authority_policy_version' => $approvedException ? config('lpj.reconciliation_exception.authority_policy.version') : null,
            ]);

            return $lockedException->fresh(['reconciliation.transaction.activity', 'resolver', 'requester', 'decisionMaker']);
        }, attempts: 3);
    }
}
