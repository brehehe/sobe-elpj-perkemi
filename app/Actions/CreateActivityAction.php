<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Enums\BudgetStatus;
use App\Enums\LpjStatus;
use App\Enums\PlanStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Lpj;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateActivityAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, array $data): Activity
    {
        return DB::transaction(function () use ($user, $data): Activity {
            $budget = Budget::query()
                ->with(['plan.mandate', 'plan.currentVersionRecord', 'currentVersionRecord.lines'])
                ->lockForUpdate()
                ->findOrFail($data['budget_id']);
            if ((! $user->isGlobalAdministrator() && $budget->unit_id !== $user->unit_id) || $budget->status !== BudgetStatus::Approved || $budget->plan->status !== PlanStatus::Approved) {
                throw ValidationException::withMessages(['budget_id' => 'Kegiatan hanya dapat dibuat dari planning dan RAB yang telah disetujui.']);
            }
            $planVersion = $budget->plan->currentVersionRecord;
            $budgetVersion = $budget->currentVersionRecord;
            if ($planVersion === null || $budgetVersion === null) {
                throw ValidationException::withMessages(['budget_id' => 'Snapshot planning atau RAB belum tersedia.']);
            }

            $picId = $data['pic_id'] ?? ($user->unit_id === $budget->unit_id ? $user->id : null);
            $picExists = $picId !== null && User::query()
                ->whereKey($picId)
                ->where('unit_id', $budget->unit_id)
                ->where('is_active', true)
                ->exists();
            if (! $picExists) {
                throw ValidationException::withMessages(['pic_id' => 'PIC aktif dari unit pemilik RAB wajib dipilih.']);
            }

            $activity = Activity::create([
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'unit_id' => $budget->unit_id,
                'mandate_id' => $budget->plan->mandate_id,
                'plan_id' => $budget->plan_id,
                'budget_id' => $budget->id,
                'pic_id' => $picId,
                'status' => ActivityStatus::Planned,
                'plan_version' => $planVersion->version,
                'budget_version' => $budgetVersion->version,
                'budget_ceiling' => $budgetVersion->total,
                'plan_snapshot' => [
                    'id' => $planVersion->id,
                    'version' => $planVersion->version,
                    'checksum' => $planVersion->checksum,
                    'activity_type' => $planVersion->activity_type,
                    'objective' => $planVersion->objective,
                    'output' => $planVersion->output,
                    'outcome' => $planVersion->outcome,
                    'target_participants' => $planVersion->target_participants,
                    'kpis' => $planVersion->kpis,
                ],
                'budget_snapshot' => [
                    'id' => $budgetVersion->id,
                    'version' => $budgetVersion->version,
                    'checksum' => $budgetVersion->checksum,
                    'total' => $budgetVersion->total,
                    'currency' => $budget->currency,
                    'lines' => $budgetVersion->lines->map->only(['id', 'line_number', 'account_code', 'description', 'quantity', 'unit', 'unit_cost', 'subtotal'])->values()->all(),
                ],
            ]);

            Lpj::create([
                'activity_id' => $activity->id,
                'owner_id' => $activity->pic_id,
                'status' => LpjStatus::Draft,
            ]);

            $task = SmartTask::create([
                'unit_id' => $activity->unit_id,
                'activity_id' => $activity->id,
                'assignee_id' => $activity->pic_id,
                'type' => 'START_ACTIVITY',
                'title' => 'Mulai pelaksanaan '.$activity->title,
                'description' => 'Periksa agenda dan ubah status kegiatan saat pelaksanaan dimulai.',
                'priority' => 'MEDIUM',
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => $activity->starts_at,
            ]);
            DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));

            $this->recordAuditEvent->handle($activity, 'activity.created', $user, null, $activity->status->value);

            return $activity->load(['mandate', 'pic', 'lpj']);
        });
    }
}
