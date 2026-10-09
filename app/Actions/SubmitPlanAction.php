<?php

namespace App\Actions;

use App\Enums\PlanStatus;
use App\Models\Mandate;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitPlanAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(?Plan $plan, User $user, array $data): PlanVersion
    {
        return DB::transaction(function () use ($plan, $user, $data): PlanVersion {
            $mandate = Mandate::query()->lockForUpdate()->findOrFail($data['mandate_id']);
            if ($mandate->status !== 'ACTIVE' || (! $user->isGlobalAdministrator() && $mandate->unit_id !== $user->unit_id)) {
                throw ValidationException::withMessages(['mandate_id' => 'Mandat aktif tidak tersedia dalam lingkup kewenangan Anda.']);
            }

            $lockedPlan = $plan === null
                ? Plan::create([
                    'mandate_id' => $data['mandate_id'],
                    'unit_id' => $mandate->unit_id,
                    'owner_id' => $user->id,
                    'code' => Str::upper($data['code']),
                    'status' => PlanStatus::Draft,
                ])
                : Plan::query()->lockForUpdate()->findOrFail($plan->id);

            $lockedPlan->loadMissing('currentVersionRecord');

            if ((! $user->isGlobalAdministrator() && $lockedPlan->unit_id !== $user->unit_id) || ! in_array($lockedPlan->status, [PlanStatus::Draft, PlanStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'Planning pada status ini tidak dapat diajukan ulang.']);
            }

            $versionNumber = $lockedPlan->current_version + 1;
            $snapshot = Arr::only($data, ['activity_type', 'objective', 'output', 'outcome', 'target_participants', 'kpis', 'schedule', 'resources', 'risks']);
            if ($lockedPlan->source_ai_review_id !== null) {
                $snapshot['source_context'] = $lockedPlan->currentVersionRecord?->source_context;
            }
            $checksum = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $version = PlanVersion::create([
                ...$snapshot,
                'plan_id' => $lockedPlan->id,
                'created_by' => $user->id,
                'version' => $versionNumber,
                'status' => PlanStatus::Submitted,
                'change_reason' => $data['change_reason'] ?? null,
                'checksum' => $checksum,
                'is_immutable' => true,
                'submitted_at' => now(),
            ]);

            $fromStatus = $lockedPlan->status->value;
            $lockedPlan->update([
                'mandate_id' => $data['mandate_id'],
                'code' => Str::upper($data['code']),
                'status' => PlanStatus::Submitted,
                'current_version' => $versionNumber,
                'submitted_at' => now(),
                'approved_at' => null,
            ]);

            $this->recordAuditEvent->handle($lockedPlan, 'plan.submitted', $user, $fromStatus, PlanStatus::Submitted->value, [
                'version' => $versionNumber,
                'checksum' => $checksum,
                'source_ai_review_id' => $lockedPlan->source_ai_review_id,
            ]);

            return $version->load('plan.mandate');
        }, attempts: 3);
    }
}
