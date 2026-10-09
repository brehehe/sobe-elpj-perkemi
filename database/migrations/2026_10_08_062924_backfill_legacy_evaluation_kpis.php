<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('evaluations')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $evaluation): void {
                if (DB::table('kpi_definitions')->where('evaluation_id', $evaluation->id)->exists()) {
                    return;
                }

                $activity = DB::table('activities')->where('id', $evaluation->activity_id)->first();
                if ($activity === null) {
                    return;
                }

                $snapshot = json_decode((string) $activity->plan_snapshot, true);
                $kpis = is_array($snapshot) ? ($snapshot['kpis'] ?? []) : [];
                if (! is_array($kpis) || $kpis === []) {
                    $planVersion = DB::table('plan_versions')
                        ->where('plan_id', $activity->plan_id)
                        ->when($activity->plan_version, fn ($query) => $query->where('version', $activity->plan_version))
                        ->orderByDesc('version')
                        ->first();
                    $kpis = $planVersion === null ? [] : json_decode((string) $planVersion->kpis, true);
                }

                $evidence = DB::table('evidence')
                    ->where('activity_id', $activity->id)
                    ->where('scan_status', 'REGISTERED')
                    ->orderBy('created_at')
                    ->first();
                if (! is_array($kpis) || $kpis === [] || $evidence === null) {
                    return;
                }

                $metrics = json_decode((string) $evaluation->metrics, true);
                $metricActual = collect(is_array($metrics) ? $metrics : [])->first(fn ($value) => is_numeric($value));
                foreach (array_values($kpis) as $position => $kpi) {
                    if (! is_array($kpi)) {
                        continue;
                    }

                    $target = (float) ($kpi['target'] ?? 0);
                    $achievement = (float) ($evaluation->score ?? 0);
                    $actual = is_numeric($metricActual) ? (float) $metricActual : ($target * $achievement / 100);
                    $definitionId = (string) Str::uuid();
                    DB::table('kpi_definitions')->insert([
                        'id' => $definitionId,
                        'evaluation_id' => $evaluation->id,
                        'position' => $position,
                        'name' => (string) ($kpi['name'] ?? 'KPI '.($position + 1)),
                        'definition' => (string) ($kpi['definition'] ?? $kpi['name'] ?? 'Indikator dari planning legacy.'),
                        'unit' => (string) ($kpi['unit'] ?? 'nilai'),
                        'baseline' => isset($kpi['baseline']) && $kpi['baseline'] !== '' ? $kpi['baseline'] : null,
                        'target' => $target,
                        'period_start' => $kpi['period_start'] ?? substr((string) $activity->starts_at, 0, 10),
                        'period_end' => $kpi['period_end'] ?? substr((string) $activity->ends_at, 0, 10),
                        'formula' => $kpi['formula'] ?? 'HIGHER_IS_BETTER',
                        'formula_version' => 'legacy-manual-v0',
                        'weight' => $kpi['weight'] ?? 1,
                        'created_at' => $evaluation->created_at,
                        'updated_at' => $evaluation->updated_at,
                    ]);
                    DB::table('kpi_results')->insert([
                        'id' => (string) Str::uuid(),
                        'kpi_definition_id' => $definitionId,
                        'evidence_id' => $evidence->id,
                        'actual' => $actual,
                        'achievement_percent' => $achievement,
                        'notes' => 'Backfill dari evaluasi legacy; nilai historis dipertahankan untuk audit.',
                        'created_at' => $evaluation->created_at,
                        'updated_at' => $evaluation->updated_at,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $definitionIds = DB::table('kpi_definitions')
            ->where('formula_version', 'legacy-manual-v0')
            ->pluck('id');
        DB::table('kpi_results')->whereIn('kpi_definition_id', $definitionIds)->delete();
        DB::table('kpi_definitions')->whereIn('id', $definitionIds)->delete();
    }
};
