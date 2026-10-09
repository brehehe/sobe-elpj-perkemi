<?php

namespace App\Actions;

use App\Enums\LpjStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Lpj;
use App\Models\User;
use App\Services\LpjSubmissionGate;
use Illuminate\Support\Str;

class ExportLpjDraftAction
{
    public function __construct(
        private LpjSubmissionGate $submissionGate,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /** @return array{filename: string, document: array<string, mixed>} */
    public function handle(Lpj $lpj, User $actor): array
    {
        abort_unless(in_array($lpj->status, [LpjStatus::Draft, LpjStatus::ReadyToSubmit, LpjStatus::Returned], true), 409, 'Ekspor draft hanya tersedia sebelum snapshot berikutnya diajukan.');

        $composer = $this->submissionGate->evaluate($lpj);
        $activity = $lpj->activity;
        $activeEvidence = $activity->evidence->whereIn('id', $composer['active_evidence_ids'])->values();
        $actualAmount = $activity->transactions->sum('amount');
        $filename = 'draft-lpj-'.Str::slug($activity->code).'-r'.$lpj->draft_revision.'.html';
        $document = [
            'generated_at' => now(),
            'lpj' => [
                'id' => $lpj->id,
                'status' => $lpj->status->value,
                'status_label' => $lpj->status->label(),
                'draft_revision' => $lpj->draft_revision,
                'draft_updated_at' => $lpj->draft_updated_at,
            ],
            'activity' => $activity->only(['id', 'code', 'title', 'description', 'location', 'starts_at', 'ends_at', 'budget_ceiling', 'plan_version', 'budget_version']),
            'unit' => $activity->unit->only(['id', 'code', 'name']),
            'owner' => $lpj->owner->only(['id', 'name', 'email']),
            'mandate' => $activity->mandate?->only(['id', 'number', 'title', 'issued_at', 'expires_at']),
            'plan' => $activity->plan?->only(['id', 'code', 'current_version', 'status']),
            'budget' => $activity->budget?->only(['id', 'code', 'current_version', 'currency', 'status']),
            'narratives' => $lpj->only(array_keys(LpjSubmissionGate::NARRATIVE_FIELDS)),
            'narrative_labels' => LpjSubmissionGate::NARRATIVE_FIELDS,
            'composer' => collect($composer)->only(['ready', 'percentage', 'policy_version', 'template', 'modules', 'errors', 'warnings'])->all(),
            'financial' => [
                'budget_ceiling' => (string) $activity->budget_ceiling,
                'actual_amount' => (string) $actualAmount,
                'variance' => (string) ((float) $activity->budget_ceiling - (float) $actualAmount),
                'transaction_count' => $activity->transactions->count(),
                'reconciled_count' => $activity->transactions
                    ->filter(fn ($transaction): bool => in_array($transaction->reconciliation_status, [ReconciliationStatus::Matched, ReconciliationStatus::Resolved], true))
                    ->count(),
            ],
            'sources' => [
                'agenda_count' => $activity->agendaItems->count(),
                'execution_record_count' => $activity->executionRecords->count(),
                'participant_count' => $activity->participants->count(),
                'evaluation_count' => $activity->evaluations->count(),
                'evidence' => $activeEvidence->map(fn ($evidence): array => [
                    'id' => $evidence->id,
                    'type' => $evidence->type,
                    'title' => $evidence->title,
                    'version' => $evidence->version,
                    'sha256' => $evidence->sha256,
                    'review_status' => $evidence->review_status->value,
                ])->all(),
            ],
        ];

        $this->recordAuditEvent->handle(
            $lpj,
            'lpj.draft_exported',
            $actor,
            payload: [
                'draft_revision' => $lpj->draft_revision,
                'completeness_percentage' => $composer['percentage'],
                'template_policy_version' => $composer['template']['policy_version'],
                'filename' => $filename,
                'evidence_ids' => $composer['active_evidence_ids'],
            ],
            authority: 'lpjs.view',
            objectVersion: 'draft-r'.$lpj->draft_revision,
        );

        return compact('filename', 'document');
    }
}
