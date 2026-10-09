<?php

namespace App\Actions;

use App\Enums\GovernancePolicyType;
use App\Models\GovernancePolicy;
use App\Models\User;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Support\Facades\DB;

class PublishGovernancePolicyAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private GovernancePolicyRegistry $registry,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(GovernancePolicyType $type, User $actor, string $version, string $changeSummary, array $settings): GovernancePolicy
    {
        return DB::transaction(function () use ($type, $actor, $version, $changeSummary, $settings): GovernancePolicy {
            $policies = GovernancePolicy::query()
                ->where('type', $type->value)
                ->lockForUpdate()
                ->get();
            $previous = $policies->firstWhere('is_active', true);

            GovernancePolicy::query()
                ->where('type', $type->value)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $policy = GovernancePolicy::create([
                'type' => $type,
                'version' => $version,
                'settings' => $settings,
                'change_summary' => $changeSummary,
                'is_active' => true,
                'created_by' => $actor->id,
                'activated_at' => now(),
            ]);

            $this->recordAuditEvent->handle(
                $policy,
                'governance_policy.published',
                $actor,
                fromStatus: $previous?->version,
                toStatus: $policy->version,
                payload: [
                    'type' => $type->value,
                    'before' => $previous?->settings,
                    'after' => $settings,
                    'change_summary' => $changeSummary,
                ],
                authority: 'admin.manage-master-data',
                objectVersion: $policy->version,
            );

            $this->registry->forget($type);

            return $policy;
        }, attempts: 3);
    }
}
