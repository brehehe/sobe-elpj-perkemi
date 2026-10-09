<?php

namespace App\Actions;

use App\Enums\KpiFormula;

class CalculateKpiAchievementAction
{
    public function handle(KpiFormula $formula, float $target, float $actual): float
    {
        $achievement = match ($formula) {
            KpiFormula::HigherIsBetter => $target === 0.0
                ? ($actual === 0.0 ? 100.0 : (float) config('evaluation.achievement_cap'))
                : ($actual / $target) * 100,
            KpiFormula::LowerIsBetter => $actual <= $target
                ? 100.0
                : ($target === 0.0 ? 0.0 : ($target / $actual) * 100),
        };

        return round(max(0, min($achievement, (float) config('evaluation.achievement_cap'))), 2);
    }
}
