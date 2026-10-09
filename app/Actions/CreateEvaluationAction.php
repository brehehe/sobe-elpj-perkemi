<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Enums\KpiFormula;
use App\Enums\LpjStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Evaluation;
use App\Models\Evidence;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEvaluationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private CalculateKpiAchievementAction $calculateKpiAchievement,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Activity $activity, User $user, array $data): Evaluation
    {
        return DB::transaction(function () use ($activity, $user, $data): Evaluation {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($lockedActivity->status !== ActivityStatus::Completed) {
                throw ValidationException::withMessages(['activity' => 'Evaluasi hanya dapat difinalisasi setelah kegiatan selesai.']);
            }
            if (! $user->isGlobalAdministrator() && $lockedActivity->pic_id !== $user->id) {
                throw ValidationException::withMessages(['evaluation' => 'Hanya PIC kegiatan yang dapat memfinalisasi evaluasi.']);
            }
            $lpjStatus = $lockedActivity->lpj()->lockForUpdate()->value('status');
            $resolvedLpjStatus = $lpjStatus instanceof LpjStatus ? $lpjStatus : ($lpjStatus === null ? null : LpjStatus::from($lpjStatus));
            if ($resolvedLpjStatus !== null && ! in_array($resolvedLpjStatus, [
                LpjStatus::Draft,
                LpjStatus::ReadyToSubmit,
                LpjStatus::Returned,
                LpjStatus::ClarificationRequired,
                LpjStatus::FindingOpen,
            ], true)) {
                throw ValidationException::withMessages(['evaluation' => 'Evaluasi tidak dapat direvisi ketika LPJ sedang atau sudah diproses final.']);
            }

            $previous = $lockedActivity->evaluations()->lockForUpdate()->latest('version')->first();
            if ($previous?->status === EvaluationStatus::Submitted) {
                throw ValidationException::withMessages(['evaluation' => 'Versi evaluasi terakhir masih menunggu review substantif.']);
            }

            $planKpis = $this->planKpis($lockedActivity);
            $results = collect($data['kpis'] ?? [])->keyBy('position');
            if ($planKpis === [] || $results->count() !== count($planKpis)) {
                throw ValidationException::withMessages(['kpis' => 'Setiap KPI pada planning wajib memiliki nilai aktual dan bukti.']);
            }

            $evidenceIds = $results->pluck('evidence_id')->unique()->values();
            $validEvidenceCount = Evidence::query()
                ->where('activity_id', $lockedActivity->id)
                ->where('scan_status', EvidenceScanStatus::Registered)
                ->whereNotIn('review_status', [EvidenceReviewStatus::Rejected->value, EvidenceReviewStatus::Superseded->value])
                ->whereIn('id', $evidenceIds)
                ->count();
            if ($validEvidenceCount !== $evidenceIds->count()) {
                throw ValidationException::withMessages(['kpis' => 'Bukti KPI harus berasal dari kegiatan ini dan sudah lolos pemeriksaan berkas.']);
            }

            $formulaVersion = (string) config('evaluation.formula_version');
            $fromStatus = $previous?->status?->value;
            $weightedAchievement = 0.0;
            $totalWeight = 0.0;
            $calculatedRows = [];

            foreach ($planKpis as $position => $planKpi) {
                $result = $results->get($position);
                if (! is_array($result)) {
                    throw ValidationException::withMessages(["kpis.{$position}" => 'Hasil KPI ini wajib dilengkapi.']);
                }

                $formula = KpiFormula::tryFrom((string) ($planKpi['formula'] ?? '')) ?? KpiFormula::HigherIsBetter;
                $target = (float) ($planKpi['target'] ?? 0);
                $actual = (float) $result['actual'];
                $weight = max((float) ($planKpi['weight'] ?? 1), 0.0001);
                $achievement = $this->calculateKpiAchievement->handle($formula, $target, $actual);
                $weightedAchievement += $achievement * $weight;
                $totalWeight += $weight;
                $calculatedRows[] = compact('position', 'planKpi', 'result', 'formula', 'target', 'actual', 'weight', 'achievement');
            }

            if ($previous !== null) {
                $previous->update(['status' => EvaluationStatus::Superseded]);
            }

            $recommendation = trim((string) ($data['recommendations'] ?? ''));
            $evaluation = $lockedActivity->evaluations()->create([
                ...Arr::only($data, [
                    'summary', 'outcomes', 'completion_percent', 'satisfaction_score',
                    'lessons_learned', 'successful_practices', 'improvement_areas',
                ]),
                'supersedes_id' => $previous?->id,
                'created_by' => $user->id,
                'version' => ($previous?->version ?? 0) + 1,
                'status' => EvaluationStatus::Submitted,
                'score' => round($weightedAchievement / $totalWeight, 2),
                'metrics' => [
                    'actual_participants' => $lockedActivity->participants()->where('attendance_status', 'ATTENDED')->count(),
                    'target_participants' => data_get($lockedActivity->plan_snapshot, 'target_participants'),
                    'kpi_count' => count($calculatedRows),
                ],
                'recommendations' => $recommendation === '' ? [] : [$recommendation],
                'formula_version' => $formulaVersion,
            ]);

            foreach ($calculatedRows as $row) {
                $planKpi = $row['planKpi'];
                $definition = $evaluation->kpiDefinitions()->create([
                    'position' => $row['position'],
                    'name' => (string) ($planKpi['name'] ?? 'KPI '.($row['position'] + 1)),
                    'definition' => (string) ($planKpi['definition'] ?? $planKpi['name'] ?? 'Indikator planning'),
                    'unit' => (string) ($planKpi['unit'] ?? 'nilai'),
                    'baseline' => isset($planKpi['baseline']) && $planKpi['baseline'] !== '' ? $planKpi['baseline'] : null,
                    'target' => $row['target'],
                    'period_start' => $planKpi['period_start'] ?? $lockedActivity->starts_at->toDateString(),
                    'period_end' => $planKpi['period_end'] ?? $lockedActivity->ends_at->toDateString(),
                    'formula' => $row['formula'],
                    'formula_version' => $formulaVersion,
                    'weight' => $row['weight'],
                ]);
                $definition->result()->create([
                    'evidence_id' => $row['result']['evidence_id'],
                    'actual' => $row['actual'],
                    'achievement_percent' => $row['achievement'],
                    'notes' => $row['result']['notes'] ?? null,
                ]);
            }

            SmartTask::query()
                ->where('activity_id', $lockedActivity->id)
                ->whereIn('type', ['COMPLETE_EVALUATION', 'RESOLVE_EVALUATION_FINDING'])
                ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                ->update(['status' => TaskStatus::Done]);
            SmartTask::query()
                ->where('activity_id', $lockedActivity->id)
                ->where('type', 'COMPLETE_LPJ')
                ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                ->update(['status' => TaskStatus::Cancelled]);

            $reviewer = User::permission('lpjs.verify.substantive')
                ->where('unit_id', $lockedActivity->unit_id)
                ->whereKeyNot($user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->first();
            $task = SmartTask::create([
                'unit_id' => $lockedActivity->unit_id,
                'activity_id' => $lockedActivity->id,
                'assignee_id' => $reviewer?->id,
                'type' => 'REVIEW_EVALUATION',
                'title' => 'Review evaluasi '.$lockedActivity->title,
                'description' => 'Periksa capaian KPI, formula, narasi outcome, dan bukti substantif.',
                'priority' => 'HIGH',
                'status' => TaskStatus::Open,
                'source' => 'WORKFLOW',
                'due_at' => now()->addDays(3),
                'metadata' => ['evaluation_id' => $evaluation->id, 'version' => $evaluation->version],
            ]);

            DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            $this->recordAuditEvent->handle(
                $evaluation,
                'evaluation.finalized',
                $user,
                $fromStatus,
                EvaluationStatus::Submitted->value,
                ['activity_id' => $lockedActivity->id, 'version' => $evaluation->version, 'formula_version' => $formulaVersion],
            );

            return $evaluation->load(['creator:id,name', 'kpiDefinitions.result.evidence:id,title,version']);
        }, attempts: 3);
    }

    /** @return list<array<string, mixed>> */
    private function planKpis(Activity $activity): array
    {
        $kpis = data_get($activity->plan_snapshot, 'kpis');
        if (is_array($kpis) && $kpis !== []) {
            return array_values($kpis);
        }

        $version = $activity->plan?->versions()
            ->when($activity->plan_version, fn ($query) => $query->where('version', $activity->plan_version))
            ->latest('version')
            ->first();

        return is_array($version?->kpis) ? array_values($version->kpis) : [];
    }
}
