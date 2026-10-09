<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Enums\PlanStatus;
use App\Enums\TaskStatus;
use App\Models\Budget;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewBudgetAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Budget $budget, User $reviewer, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $reviewer, $data): Budget {
            $lockedBudget = Budget::query()->lockForUpdate()->findOrFail($budget->id);
            if ($lockedBudget->status !== BudgetStatus::Submitted || $lockedBudget->owner_id === $reviewer->id) {
                throw ValidationException::withMessages(['decision' => 'RAB tidak dapat direview oleh pengguna atau pada status ini.']);
            }
            if ($lockedBudget->plan()->firstOrFail()->status !== PlanStatus::Approved) {
                throw ValidationException::withMessages(['decision' => 'Planning harus disetujui sebelum keputusan review RAB dicatat.']);
            }

            $decision = BudgetStatus::from($data['decision']);
            $version = $lockedBudget->currentVersionRecord()->firstOrFail();
            $version->update([
                'reviewed_by' => $reviewer->id,
                'status' => $decision,
                'review_notes' => $data['notes'] ?? null,
                'reviewed_at' => now(),
            ]);
            $lockedBudget->update([
                'status' => $decision,
                'approved_at' => $decision === BudgetStatus::Approved ? now() : null,
            ]);

            if ($decision === BudgetStatus::Returned) {
                $task = SmartTask::create([
                    'unit_id' => $lockedBudget->unit_id,
                    'assignee_id' => $lockedBudget->owner_id,
                    'type' => 'BUDGET_REVISION',
                    'title' => 'Perbaiki RAB '.$lockedBudget->code,
                    'description' => $data['notes'] ?? 'RAB dikembalikan untuk diperbaiki.',
                    'priority' => 'HIGH',
                    'status' => TaskStatus::Open,
                    'source' => 'WORKFLOW',
                    'due_at' => now()->addDays(3),
                    'metadata' => ['budget_id' => $lockedBudget->id, 'version' => $version->version],
                ]);
                DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            }

            $this->recordAuditEvent->handle($lockedBudget, 'budget.reviewed', $reviewer, BudgetStatus::Submitted->value, $decision->value, [
                'version' => $version->version,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedBudget->fresh(['currentVersionRecord.lines', 'plan']);
        }, attempts: 3);
    }
}
