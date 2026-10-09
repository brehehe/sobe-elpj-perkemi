<?php

namespace App\Actions;

use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\TaskStatus;
use App\Models\BudgetLine;
use App\Models\FinancialTransaction;
use App\Models\Reconciliation;
use App\Models\ReconciliationException;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;

class ReconcileTransactionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(FinancialTransaction $transaction, User $user, array $data): Reconciliation
    {
        return DB::transaction(function () use ($transaction, $user, $data): Reconciliation {
            $lockedTransaction = FinancialTransaction::query()
                ->with(['activity.evidence', 'reconciliation.exceptions'])
                ->lockForUpdate()
                ->findOrFail($transaction->id);
            $budgetLine = BudgetLine::query()->with('budgetVersion.budget')->findOrFail($data['budget_line_id']);

            $expectedAmount = (string) $budgetLine->subtotal;
            $actualAmount = (string) $lockedTransaction->amount;
            $variance = bcsub($actualAmount, $expectedAmount, 2);
            $checks = [
                'amount' => bccomp($expectedAmount, $actualAmount, 2) === 0,
                'reference' => filled($lockedTransaction->reference_number),
                'evidence' => $lockedTransaction->activity->evidence->isNotEmpty(),
                'account' => blank($lockedTransaction->account_code) || $lockedTransaction->account_code === $budgetLine->account_code,
            ];
            $status = collect($checks)->every(fn (bool $passed): bool => $passed)
                ? ReconciliationStatus::Matched
                : ReconciliationStatus::Exception;

            $reconciliation = Reconciliation::updateOrCreate(
                ['financial_transaction_id' => $lockedTransaction->id],
                [
                    'budget_line_id' => $budgetLine->id,
                    'reconciled_by' => $user->id,
                    'status' => $status,
                    'expected_amount' => $expectedAmount,
                    'actual_amount' => $actualAmount,
                    'variance' => $variance,
                    'checks' => $checks,
                    'notes' => $data['notes'] ?? null,
                    'reconciled_at' => now(),
                ],
            );
            $lockedTransaction->update([
                'budget_line_id' => $budgetLine->id,
                'reconciliation_status' => $status,
            ]);

            if ($status === ReconciliationStatus::Matched) {
                $resolvedExceptionIds = $reconciliation->exceptions()
                    ->whereIn('status', ReconciliationExceptionStatus::BLOCKING_VALUES)
                    ->pluck('id');
                $reconciliation->exceptions()
                    ->whereIn('status', ReconciliationExceptionStatus::BLOCKING_VALUES)
                    ->update([
                        'status' => ReconciliationExceptionStatus::Resolved,
                        'resolution_type' => 'RECONCILED',
                        'resolution' => 'Pemeriksaan ulang telah cocok dengan RAB dan sumber bukti.',
                        'resolved_by' => $user->id,
                        'requested_by' => null,
                        'decided_by' => null,
                        'decision' => null,
                        'decision_notes' => null,
                        'authority_policy_version' => null,
                        'is_approved_exception' => false,
                        'requested_at' => null,
                        'decided_at' => null,
                        'resolved_at' => now(),
                    ]);
                foreach ($resolvedExceptionIds as $resolvedExceptionId) {
                    SmartTask::query()
                        ->where('metadata->reconciliation_exception_id', $resolvedExceptionId)
                        ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
                        ->update(['status' => TaskStatus::Done]);
                }
            } else {
                $exception = $reconciliation->exceptions()->whereIn('status', ReconciliationExceptionStatus::BLOCKING_VALUES)->first();
                if ($exception === null) {
                    $failedChecks = collect($checks)->filter(fn (bool $passed): bool => ! $passed)->keys()->implode(', ');
                    $exception = ReconciliationException::create([
                        'reconciliation_id' => $reconciliation->id,
                        'owner_id' => $user->id,
                        'category' => bccomp($variance, '0.00', 2) !== 0 ? 'AMOUNT_VARIANCE' : 'INCOMPLETE_SOURCE',
                        'severity' => bccomp(number_format(abs((float) $variance), 2, '.', ''), '1000000.00', 2) >= 0 ? 'HIGH' : 'MEDIUM',
                        'status' => ReconciliationExceptionStatus::Open,
                        'detail' => 'Pemeriksaan gagal: '.$failedChecks.'.',
                        'due_at' => now()->addDays(3),
                    ]);
                    $task = SmartTask::create([
                        'unit_id' => $lockedTransaction->activity->unit_id,
                        'activity_id' => $lockedTransaction->activity_id,
                        'assignee_id' => $user->id,
                        'type' => 'RECONCILIATION_EXCEPTION',
                        'title' => 'Selesaikan exception transaksi '.$lockedTransaction->reference_number,
                        'description' => $exception->detail,
                        'priority' => $exception->severity,
                        'status' => TaskStatus::Open,
                        'source' => 'WORKFLOW',
                        'due_at' => $exception->due_at,
                        'metadata' => ['reconciliation_exception_id' => $exception->id],
                    ]);
                    DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
                }
            }

            $this->recordAuditEvent->handle($reconciliation, 'finance.reconciled', $user, null, $status->value, [
                'transaction_id' => $lockedTransaction->id,
                'budget_line_id' => $budgetLine->id,
                'variance' => $variance,
                'checks' => $checks,
            ]);

            return $reconciliation->fresh(['transaction.activity', 'budgetLine', 'exceptions']);
        }, attempts: 3);
    }
}
