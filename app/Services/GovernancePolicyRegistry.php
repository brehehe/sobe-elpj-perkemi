<?php

namespace App\Services;

use App\Enums\GovernancePolicyType;
use App\Models\GovernancePolicy;
use Illuminate\Support\Facades\Schema;

class GovernancePolicyRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $resolved = [];

    /** @return array<string, mixed> */
    public function get(GovernancePolicyType $type): array
    {
        if (isset($this->resolved[$type->value])) {
            return $this->resolved[$type->value];
        }

        if (! Schema::hasTable('governance_policies')) {
            return $this->resolved[$type->value] = $this->fallback($type);
        }

        $policy = GovernancePolicy::query()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->latest('activated_at')
            ->first();

        if ($policy !== null) {
            return $this->resolved[$type->value] = [
                'version' => $policy->version,
                ...$policy->settings,
            ];
        }

        return $this->resolved[$type->value] = $this->fallback($type);
    }

    public function forget(GovernancePolicyType $type): void
    {
        unset($this->resolved[$type->value]);
    }

    /** @return array<string, mixed> */
    private function fallback(GovernancePolicyType $type): array
    {
        $key = match ($type) {
            GovernancePolicyType::Verification => 'lpj.verification_policy',
            GovernancePolicyType::Workflow => 'lpj.workflow_tasks',
            GovernancePolicyType::Templates => 'lpj.template_policy',
            GovernancePolicyType::Retention => 'lpj.archive_policy',
            GovernancePolicyType::Ai => 'lpj.ai_policy',
            GovernancePolicyType::System => 'lpj.system_policy',
        };

        $fallback = config($key, []);

        if ($type === GovernancePolicyType::Ai) {
            $fallback['daily_token_budget'] = (int) floor((float) config('services.openai.daily_budget', 0));
            $fallback['monthly_token_budget'] = (int) floor((float) config('services.openai.monthly_budget', 0));
        }

        return $fallback;
    }
}
