<?php

namespace App\Actions;

use App\Enums\ReconciliationExceptionDecision;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\TaskStatus;
use App\Models\FinancialTransaction;
use App\Models\ReconciliationException;
use App\Models\SmartTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideReconciliationExceptionAction
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

            if ($lockedException->status !== ReconciliationExceptionStatus::PendingApproval) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan pengecualian yang menunggu keputusan yang dapat diproses.']);
            }

            if (in_array($user->id, [$lockedException->requested_by, $lockedException->owner_id], true)) {
                throw ValidationException::withMessages(['approver' => 'Pengaju atau pemilik exception tidak boleh memutuskan permintaannya sendiri.']);
            }

            $decision = ReconciliationExceptionDecision::from($data['decision']);
            $approved = $decision === ReconciliationExceptionDecision::Approved;
            $toStatus = $approved
                ? ReconciliationExceptionStatus::ApprovedException
                : ReconciliationExceptionStatus::Rejected;
            $fromStatus = $lockedException->status->value;

            $lockedException->update([
                'decided_by' => $user->id,
                'decision' => $decision,
                'decision_notes' => $data['notes'],
                'authority_policy_version' => config('lpj.reconciliation_exception.authority_policy.version'),
                'is_approved_exception' => $approved,
                'status' => $toStatus,
                'decided_at' => now(),
                'resolved_at' => $approved ? now() : null,
            ]);

            if ($approved) {
                $lockedException->reconciliation->update(['status' => ReconciliationStatus::Resolved]);
                $lockedException->reconciliation->transaction->update(['reconciliation_status' => ReconciliationStatus::Resolved]);
                SmartTask::query()
                    ->where('metadata->reconciliation_exception_id', $lockedException->id)
                    ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
                    ->update(['status' => TaskStatus::Done]);
            } else {
                $lockedException->reconciliation->update(['status' => ReconciliationStatus::Exception]);
                $lockedException->reconciliation->transaction->update(['reconciliation_status' => ReconciliationStatus::Exception]);
                SmartTask::query()
                    ->where('metadata->reconciliation_exception_id', $lockedException->id)
                    ->where('status', TaskStatus::Done->value)
                    ->update(['status' => TaskStatus::Open]);
            }

            $this->recordAuditEvent->handle($lockedException, 'finance.reconciliation_exception_decided', $user, $fromStatus, $toStatus->value, [
                'decision' => $decision->value,
                'authority_policy_version' => config('lpj.reconciliation_exception.authority_policy.version'),
            ]);

            return $lockedException->fresh(['reconciliation.transaction.activity', 'owner', 'resolver', 'requester', 'decisionMaker']);
        }, attempts: 3);
    }
}
