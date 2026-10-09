<?php

namespace App\Actions;

use App\Enums\PlanStatus;
use App\Enums\TaskStatus;
use App\Models\Plan;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPlanAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Plan $plan, User $reviewer, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $reviewer, $data): Plan {
            $lockedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status !== PlanStatus::Submitted || $lockedPlan->owner_id === $reviewer->id) {
                throw ValidationException::withMessages(['decision' => 'Planning tidak dapat direview oleh pengguna atau pada status ini.']);
            }

            $decision = PlanStatus::from($data['decision']);
            $version = $lockedPlan->currentVersionRecord()->firstOrFail();
            $version->update([
                'reviewed_by' => $reviewer->id,
                'status' => $decision,
                'review_notes' => $data['notes'] ?? null,
                'reviewed_at' => now(),
            ]);
            $lockedPlan->update([
                'status' => $decision,
                'approved_at' => $decision === PlanStatus::Approved ? now() : null,
            ]);

            if ($decision === PlanStatus::Returned) {
                $task = SmartTask::create([
                    'unit_id' => $lockedPlan->unit_id,
                    'assignee_id' => $lockedPlan->owner_id,
                    'type' => 'PLAN_REVISION',
                    'title' => 'Perbaiki planning '.$lockedPlan->code,
                    'description' => $data['notes'] ?? 'Planning dikembalikan untuk diperbaiki.',
                    'priority' => 'HIGH',
                    'status' => TaskStatus::Open,
                    'source' => 'WORKFLOW',
                    'due_at' => now()->addDays(3),
                    'metadata' => ['plan_id' => $lockedPlan->id, 'version' => $version->version],
                ]);
                DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            }

            $this->recordAuditEvent->handle($lockedPlan, 'plan.reviewed', $reviewer, PlanStatus::Submitted->value, $decision->value, [
                'version' => $version->version,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedPlan->fresh(['currentVersionRecord', 'mandate']);
        }, attempts: 3);
    }
}
