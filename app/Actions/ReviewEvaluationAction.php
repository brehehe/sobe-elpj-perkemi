<?php

namespace App\Actions;

use App\Enums\EvaluationReviewDecision;
use App\Enums\EvaluationStatus;
use App\Enums\TaskStatus;
use App\Models\Evaluation;
use App\Models\SmartTask;
use App\Models\User;
use App\Notifications\WorkflowTaskAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewEvaluationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Evaluation $evaluation, User $user, array $data): Evaluation
    {
        return DB::transaction(function () use ($evaluation, $user, $data): Evaluation {
            $lockedEvaluation = Evaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            if ($lockedEvaluation->status !== EvaluationStatus::Submitted) {
                throw ValidationException::withMessages(['decision' => 'Hanya evaluasi yang menunggu review dapat diperiksa.']);
            }
            if ($lockedEvaluation->created_by === $user->id) {
                throw ValidationException::withMessages(['reviewer' => 'PIC tidak boleh memeriksa evaluasi yang dibuatnya sendiri.']);
            }

            $decision = EvaluationReviewDecision::from((string) $data['decision']);
            $status = match ($decision) {
                EvaluationReviewDecision::Accepted => EvaluationStatus::Accepted,
                EvaluationReviewDecision::ClarificationRequired => EvaluationStatus::ClarificationRequired,
                EvaluationReviewDecision::Finding => EvaluationStatus::FindingOpen,
            };
            $lockedEvaluation->update([
                'status' => $status,
                'reviewed_by' => $user->id,
                'review_notes' => $data['notes'] ?? null,
                'reviewed_at' => now(),
                'accepted_at' => $decision === EvaluationReviewDecision::Accepted ? now() : null,
            ]);

            SmartTask::query()
                ->where('activity_id', $lockedEvaluation->activity_id)
                ->where('type', 'REVIEW_EVALUATION')
                ->where('metadata->evaluation_id', $lockedEvaluation->id)
                ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
                ->update(['status' => TaskStatus::Done]);

            $task = $decision === EvaluationReviewDecision::Accepted
                ? $this->createLpjTask($lockedEvaluation)
                : $this->createCorrectionTask($lockedEvaluation, $decision, $data);

            if ($task !== null) {
                DB::afterCommit(fn () => $task->assignee?->notify(new WorkflowTaskAssigned($task)));
            }

            $this->recordAuditEvent->handle(
                $lockedEvaluation,
                'evaluation.reviewed',
                $user,
                EvaluationStatus::Submitted->value,
                $status->value,
                [
                    'activity_id' => $lockedEvaluation->activity_id,
                    'decision' => $decision->value,
                    'notes' => $data['notes'] ?? null,
                    'severity' => $data['severity'] ?? null,
                    'due_at' => $data['due_at'] ?? null,
                ],
            );

            return $lockedEvaluation->fresh(['creator:id,name', 'reviewer:id,name', 'kpiDefinitions.result.evidence:id,title,version']);
        }, attempts: 3);
    }

    private function createLpjTask(Evaluation $evaluation): ?SmartTask
    {
        $activity = $evaluation->activity;
        $existing = SmartTask::query()
            ->where('activity_id', $activity->id)
            ->where('type', 'COMPLETE_LPJ')
            ->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])
            ->first();
        if ($existing !== null) {
            return null;
        }

        return SmartTask::create([
            'unit_id' => $activity->unit_id,
            'activity_id' => $activity->id,
            'assignee_id' => $activity->pic_id,
            'type' => 'COMPLETE_LPJ',
            'title' => 'Lengkapi dan ajukan LPJ '.$activity->title,
            'description' => 'Evaluasi substantif diterima. Periksa data sumber sebelum membuat snapshot LPJ.',
            'priority' => 'HIGH',
            'status' => TaskStatus::Open,
            'source' => 'WORKFLOW',
            'due_at' => now()->addDays(7),
            'metadata' => ['evaluation_id' => $evaluation->id, 'version' => $evaluation->version],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createCorrectionTask(Evaluation $evaluation, EvaluationReviewDecision $decision, array $data): SmartTask
    {
        $activity = $evaluation->activity;
        $finding = $decision === EvaluationReviewDecision::Finding;

        return SmartTask::create([
            'unit_id' => $activity->unit_id,
            'activity_id' => $activity->id,
            'assignee_id' => $activity->pic_id,
            'type' => $finding ? 'RESOLVE_EVALUATION_FINDING' : 'COMPLETE_EVALUATION',
            'title' => ($finding ? 'Tindak lanjuti finding evaluasi ' : 'Perbaiki evaluasi ').$activity->title,
            'description' => (string) ($data['notes'] ?? 'Lengkapi klarifikasi substantif dan ajukan versi evaluasi baru.'),
            'priority' => $finding ? (string) $data['severity'] : 'HIGH',
            'status' => TaskStatus::Open,
            'source' => 'WORKFLOW',
            'due_at' => isset($data['due_at']) ? Carbon::parse($data['due_at'])->endOfDay() : now()->addDays(3),
            'metadata' => [
                'evaluation_id' => $evaluation->id,
                'evaluation_version' => $evaluation->version,
                'review_decision' => $decision->value,
            ],
        ]);
    }
}
