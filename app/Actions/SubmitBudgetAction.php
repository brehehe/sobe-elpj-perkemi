<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Enums\PlanStatus;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitBudgetAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(?Budget $budget, User $user, array $data): BudgetVersion
    {
        return DB::transaction(function () use ($budget, $user, $data): BudgetVersion {
            $plan = Plan::query()->lockForUpdate()->findOrFail($data['plan_id']);
            if ((! $user->isGlobalAdministrator() && $plan->unit_id !== $user->unit_id) || ! in_array($plan->status, [PlanStatus::Submitted, PlanStatus::Approved], true)) {
                throw ValidationException::withMessages(['plan_id' => 'RAB hanya dapat dibuat dari planning yang sudah diajukan atau disetujui.']);
            }

            $lockedBudget = $budget === null
                ? Budget::create([
                    'plan_id' => $plan->id,
                    'unit_id' => $plan->unit_id,
                    'owner_id' => $user->id,
                    'code' => Str::upper($data['code']),
                    'status' => BudgetStatus::Draft,
                    'currency' => Str::upper($data['currency']),
                ])
                : Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if ($lockedBudget->plan_id !== $plan->id || ! in_array($lockedBudget->status, [BudgetStatus::Draft, BudgetStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'RAB pada status ini tidak dapat diajukan ulang.']);
            }

            $lines = collect($data['lines'])->values()->map(function (array $line, int $index): array {
                return [
                    'line_number' => $index + 1,
                    'account_code' => $line['account_code'],
                    'description' => $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_cost' => (string) $line['unit_cost'],
                    'subtotal' => bcmul((string) $line['quantity'], (string) $line['unit_cost'], 2),
                ];
            });
            $total = $lines->reduce(fn (string $carry, array $line): string => bcadd($carry, $line['subtotal'], 2), '0.00');
            $checksum = hash('sha256', json_encode($lines->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $versionNumber = $lockedBudget->current_version + 1;

            $version = BudgetVersion::create([
                'budget_id' => $lockedBudget->id,
                'created_by' => $user->id,
                'version' => $versionNumber,
                'status' => BudgetStatus::Submitted,
                'total' => $total,
                'change_reason' => $data['change_reason'] ?? null,
                'checksum' => $checksum,
                'is_immutable' => true,
                'submitted_at' => now(),
            ]);
            $version->lines()->createMany($lines->all());

            $fromStatus = $lockedBudget->status->value;
            $lockedBudget->update([
                'code' => Str::upper($data['code']),
                'currency' => Str::upper($data['currency']),
                'status' => BudgetStatus::Submitted,
                'current_version' => $versionNumber,
                'submitted_at' => now(),
                'approved_at' => null,
            ]);
            $this->recordAuditEvent->handle($lockedBudget, 'budget.submitted', $user, $fromStatus, BudgetStatus::Submitted->value, [
                'version' => $versionNumber,
                'total' => $total,
                'checksum' => $checksum,
            ]);

            return $version->load(['budget.plan', 'lines']);
        }, attempts: 3);
    }
}
