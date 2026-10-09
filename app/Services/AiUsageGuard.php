<?php

namespace App\Services;

use App\Enums\GovernancePolicyType;
use App\Models\AiReview;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AiUsageGuard
{
    public function __construct(private GovernancePolicyRegistry $policyRegistry) {}

    public function assertCanRun(): void
    {
        $status = $this->status();

        if ($status['enabled'] && $status['daily_blocked']) {
            throw ValidationException::withMessages([
                'type' => 'Batas token OpenAI harian telah tercapai. Workflow inti tetap tersedia; coba lagi setelah anggaran harian diperbarui.',
            ]);
        }

        if ($status['enabled'] && $status['monthly_blocked']) {
            throw ValidationException::withMessages([
                'type' => 'Batas token OpenAI bulanan telah tercapai. Workflow inti tetap tersedia; coba lagi setelah anggaran bulanan diperbarui.',
            ]);
        }
    }

    /** @return array{enabled: bool, policy_version: string, used_tokens: int, limit_tokens: int|null, remaining_tokens: int|null, monthly_used_tokens: int, monthly_limit_tokens: int|null, monthly_remaining_tokens: int|null, warning_percent: int, daily_warning: bool, monthly_warning: bool, warning: bool, daily_blocked: bool, monthly_blocked: bool, blocked: bool, disclaimer: string} */
    public function status(): array
    {
        $policy = $this->policyRegistry->get(GovernancePolicyType::Ai);
        $dailyLimit = (int) ($policy['daily_token_budget'] ?? 0);
        $monthlyLimit = (int) ($policy['monthly_token_budget'] ?? 0);
        $warningPercent = (int) ($policy['warning_percent'] ?? 80);
        $enabled = (bool) ($policy['external_provider_enabled'] ?? false)
            && (bool) config('services.openai.enabled')
            && filled(config('services.openai.key'));
        $dailyUsed = $enabled ? $this->usedTokensSince(now()->startOfDay()) : 0;
        $monthlyUsed = $enabled ? $this->usedTokensSince(now()->startOfMonth()) : 0;
        $dailyRemaining = $enabled && $dailyLimit > 0 ? max($dailyLimit - $dailyUsed, 0) : null;
        $monthlyRemaining = $enabled && $monthlyLimit > 0 ? max($monthlyLimit - $monthlyUsed, 0) : null;
        $dailyWarning = $enabled && $dailyLimit > 0 && $dailyUsed >= (int) floor($dailyLimit * ($warningPercent / 100));
        $monthlyWarning = $enabled && $monthlyLimit > 0 && $monthlyUsed >= (int) floor($monthlyLimit * ($warningPercent / 100));
        $dailyBlocked = $enabled && $dailyLimit > 0 && $dailyUsed >= $dailyLimit;
        $monthlyBlocked = $enabled && $monthlyLimit > 0 && $monthlyUsed >= $monthlyLimit;

        return [
            'enabled' => $enabled,
            'policy_version' => (string) $policy['version'],
            'used_tokens' => $dailyUsed,
            'limit_tokens' => $enabled && $dailyLimit > 0 ? $dailyLimit : null,
            'remaining_tokens' => $dailyRemaining,
            'monthly_used_tokens' => $monthlyUsed,
            'monthly_limit_tokens' => $enabled && $monthlyLimit > 0 ? $monthlyLimit : null,
            'monthly_remaining_tokens' => $monthlyRemaining,
            'warning_percent' => $warningPercent,
            'daily_warning' => $dailyWarning,
            'monthly_warning' => $monthlyWarning,
            'warning' => $dailyWarning || $monthlyWarning,
            'daily_blocked' => $dailyBlocked,
            'monthly_blocked' => $monthlyBlocked,
            'blocked' => $dailyBlocked || $monthlyBlocked,
            'disclaimer' => (string) ($policy['review_disclaimer'] ?? 'Hasil AI wajib ditinjau manusia.'),
        ];
    }

    private function usedTokensSince(DateTimeInterface $start): int
    {
        return AiReview::query()
            ->where('created_at', '>=', $start)
            ->whereNotNull('usage')
            ->get(['usage'])
            ->sum(function (AiReview $review): int {
                $total = Arr::get($review->usage, 'total_tokens');

                if (is_numeric($total)) {
                    return (int) $total;
                }

                return (int) Arr::get($review->usage, 'input_tokens', 0)
                    + (int) Arr::get($review->usage, 'output_tokens', 0);
            });
    }
}
