<?php

namespace App\Actions;

use App\Enums\AiReviewStatus;
use App\Enums\PlanStatus;
use App\Models\AiReview;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePlanningDraftFromAiReviewAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(AiReview $review, User $user): Plan
    {
        return DB::transaction(function () use ($review, $user): Plan {
            $lockedReview = AiReview::query()
                ->with(['activity.plan.currentVersionRecord', 'planningDraft'])
                ->lockForUpdate()
                ->findOrFail($review->id);

            if ($lockedReview->planningDraft !== null) {
                return $lockedReview->planningDraft;
            }

            if ($lockedReview->status !== AiReviewStatus::Succeeded || ! in_array($lockedReview->human_review_status, ['ACCEPTED', 'MODIFIED'], true)) {
                throw ValidationException::withMessages([
                    'recommendation' => 'Hanya rekomendasi yang sudah diterima atau diubah reviewer yang dapat dijadikan draft planning.',
                ]);
            }

            $sourcePlan = $lockedReview->activity->plan;
            $sourceVersion = $sourcePlan?->currentVersionRecord;

            if ($sourcePlan === null || $sourceVersion === null) {
                throw ValidationException::withMessages([
                    'recommendation' => 'Sumber planning kegiatan tidak tersedia untuk membuat draft.',
                ]);
            }

            $reviewedOutput = $lockedReview->reviewed_output ?? $lockedReview->output ?? [];
            $planningBaseline = Arr::get($reviewedOutput, 'planning_baseline', []);
            $summary = trim((string) Arr::get($reviewedOutput, 'summary', ''));
            $recommendations = collect(Arr::get($reviewedOutput, 'recommendations', []))
                ->filter(fn (mixed $recommendation): bool => is_string($recommendation))
                ->map(fn (string $recommendation): string => trim($recommendation))
                ->filter()
                ->values();
            $identifiedRisks = collect(Arr::get($reviewedOutput, 'risks', []))
                ->filter(fn (mixed $risk): bool => is_string($risk))
                ->map(fn (string $risk): array => [
                    'risk' => trim($risk),
                    'mitigation' => 'Tetapkan kontrol dan penanggung jawab sebelum draft diajukan.',
                ])
                ->filter(fn (array $risk): bool => $risk['risk'] !== '')
                ->values()
                ->all();
            $sourceContext = [
                'ai_review_id' => $lockedReview->id,
                'human_review_status' => $lockedReview->human_review_status,
                'reviewed_by' => $lockedReview->reviewed_by,
                'reviewed_at' => $lockedReview->reviewed_at?->toIso8601String(),
                'model' => $lockedReview->model,
                'prompt_hash' => $lockedReview->prompt_hash,
                'prompt_version' => $lockedReview->prompt_version,
                'source_activity_id' => $lockedReview->activity_id,
                'source_activity_code' => $lockedReview->activity->code,
                'source_lpj_version_id' => $lockedReview->lpj_version_id,
                'sources' => $lockedReview->input_sources,
            ];
            $snapshot = [
                'activity_type' => $lockedReview->type === 'NEXT_ACTIVITY'
                    ? (string) Arr::get($planningBaseline, 'activity_type', $sourceVersion->activity_type)
                    : $sourceVersion->activity_type,
                'objective' => $summary !== '' ? $summary : $sourceVersion->objective,
                'output' => $recommendations->isNotEmpty()
                    ? $recommendations->map(fn (string $item, int $index): string => ($index + 1).'. '.$item)->implode("\n")
                    : $sourceVersion->output,
                'outcome' => $sourceVersion->outcome,
                'target_participants' => $lockedReview->type === 'NEXT_ACTIVITY'
                    ? max(1, (int) Arr::get($planningBaseline, 'target_participants', $sourceVersion->target_participants))
                    : $sourceVersion->target_participants,
                'kpis' => $sourceVersion->kpis,
                'schedule' => null,
                'resources' => $sourceVersion->resources ?? [],
                'risks' => $identifiedRisks !== [] ? $identifiedRisks : ($sourceVersion->risks ?? []),
                'source_context' => $sourceContext,
            ];
            $plan = Plan::create([
                'mandate_id' => $sourcePlan->mandate_id,
                'unit_id' => $lockedReview->activity->unit_id,
                'owner_id' => $user->id,
                'source_ai_review_id' => $lockedReview->id,
                'code' => $this->draftCode($lockedReview),
                'status' => PlanStatus::Draft,
                'current_version' => 0,
            ]);
            $checksum = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            PlanVersion::create([
                ...$snapshot,
                'plan_id' => $plan->id,
                'created_by' => $user->id,
                'version' => 0,
                'status' => PlanStatus::Draft,
                'change_reason' => 'Rancangan awal dari rekomendasi intelligence yang telah direview manusia.',
                'checksum' => $checksum,
                'is_immutable' => false,
            ]);

            $this->recordAuditEvent->handle($plan, 'plan.ai_draft_created', $user, null, PlanStatus::Draft->value, [
                'ai_review_id' => $lockedReview->id,
                'source_activity_id' => $lockedReview->activity_id,
                'source_lpj_version_id' => $lockedReview->lpj_version_id,
                'review_status' => $lockedReview->human_review_status,
                'draft_checksum' => $checksum,
            ]);

            return $plan->load(['currentVersionRecord', 'sourceAiReview']);
        }, attempts: 3);
    }

    private function draftCode(AiReview $review): string
    {
        $activityCode = preg_replace('/[^A-Z0-9]+/', '-', Str::upper($review->activity->code)) ?: 'ACTIVITY';
        $base = Str::limit('AI-'.$activityCode.'-'.Str::upper(Str::substr($review->id, 0, 8)), 46, '');
        $code = $base;
        $suffix = 1;

        while (Plan::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
