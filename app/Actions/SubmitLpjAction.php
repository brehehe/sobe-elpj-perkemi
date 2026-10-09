<?php

namespace App\Actions;

use App\Enums\GovernancePolicyType;
use App\Enums\LpjStatus;
use App\Enums\VerificationStage;
use App\Enums\VerificationStatus;
use App\Models\Lpj;
use App\Models\LpjVersion;
use App\Models\User;
use App\Services\GovernancePolicyRegistry;
use App\Services\LpjSubmissionGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitLpjAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private LpjSubmissionGate $submissionGate,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data = []): LpjVersion
    {
        return DB::transaction(function () use ($lpj, $user, $data): LpjVersion {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            $allowed = [LpjStatus::Draft, LpjStatus::ReadyToSubmit, LpjStatus::Returned];

            if (! in_array($lockedLpj->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'LPJ pada status ini tidak dapat diajukan.']);
            }

            $precheck = $this->submissionGate->evaluate($lockedLpj);
            if (! $precheck['ready']) {
                throw ValidationException::withMessages($precheck['errors']);
            }

            $activity = $lockedLpj->activity;
            $activeEvidence = $activity->evidence->whereIn('id', $precheck['active_evidence_ids'])->values();
            $currentEvaluation = $activity->evaluations->firstWhere('id', $precheck['current_evaluation_id']);
            $systemChecks = collect($precheck['checks'])->map(fn (array $check): array => [
                'item' => $check['label'],
                'result' => $check['result'],
                'detail' => $check['detail'],
                'source_reference' => $check['source_reference'],
            ])->values()->all();

            $totalAmount = (string) $activity->transactions->sum('amount');
            $verificationPolicy = $this->policyRegistry->get(GovernancePolicyType::Verification);
            $verificationStages = $verificationPolicy['stages'] ?? [];
            $snapshot = [
                'activity' => $activity->only(['id', 'code', 'title', 'description', 'location', 'starts_at', 'ends_at', 'plan_version', 'budget_version', 'budget_ceiling']),
                'mandate' => $activity->mandate->only(['id', 'number', 'title', 'issued_at', 'expires_at']),
                'plan' => [
                    ...$activity->plan->only(['id', 'code', 'current_version', 'status']),
                    'version' => $activity->plan->currentVersionRecord?->toArray(),
                ],
                'budget' => [
                    ...$activity->budget->only(['id', 'code', 'current_version', 'currency', 'status']),
                    'version' => $activity->budget->currentVersionRecord?->toArray(),
                ],
                'agenda' => $activity->agendaItems->map->only(['id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'status', 'notes', 'completed_at'])->values()->all(),
                'execution_records' => $activity->executionRecords->map->only(['id', 'type', 'title', 'details', 'severity', 'progress_percent', 'status', 'occurred_at', 'resolution_notes', 'resolved_at'])->values()->all(),
                'participants' => $activity->participants->map->only(['id', 'name', 'organization', 'role', 'attendance_status'])->values()->all(),
                'transactions' => $activity->transactions->map(fn ($transaction) => [
                    ...$transaction->only(['id', 'source', 'external_id', 'reference_number', 'account_code', 'description', 'transaction_date', 'amount', 'currency', 'reconciliation_status']),
                    'reconciliation' => $transaction->reconciliation === null ? null : [
                        ...$transaction->reconciliation->only(['id', 'budget_line_id', 'status', 'expected_amount', 'actual_amount', 'variance', 'checks', 'reconciled_at']),
                        'exceptions' => $transaction->reconciliation->exceptions->map->only([
                            'id', 'category', 'severity', 'status', 'detail', 'resolution_type', 'resolution',
                            'is_approved_exception', 'decision', 'authority_policy_version', 'decision_notes',
                            'requested_by', 'decided_by', 'requested_at', 'decided_at', 'resolved_at',
                        ])->values()->all(),
                    ],
                ])->values()->all(),
                'evidence' => $activeEvidence->map->only(['id', 'supersedes_id', 'type', 'version', 'title', 'original_name', 'mime_type', 'detected_mime_type', 'size', 'sha256', 'scan_status', 'scan_engine', 'scan_notes', 'scanned_at', 'linked_at', 'review_status', 'access_classification'])->values()->all(),
                'evaluations' => $currentEvaluation === null ? [] : [[
                    ...$currentEvaluation->only([
                        'id', 'version', 'status', 'summary', 'outcomes', 'completion_percent',
                        'satisfaction_score', 'lessons_learned', 'successful_practices',
                        'improvement_areas', 'score', 'metrics', 'recommendations',
                        'formula_version', 'reviewed_by', 'review_notes', 'reviewed_at', 'accepted_at',
                    ]),
                    'kpis' => $currentEvaluation->kpiDefinitions->map(fn ($definition) => [
                        ...$definition->only([
                            'id', 'position', 'name', 'definition', 'unit', 'baseline', 'target',
                            'period_start', 'period_end', 'formula', 'formula_version', 'weight',
                        ]),
                        'result' => $definition->result?->only(['id', 'actual', 'achievement_percent', 'notes', 'evidence_id']),
                        'evidence' => $definition->result?->evidence?->only(['id', 'title', 'version', 'sha256']),
                    ])->values()->all(),
                ]],
                'narratives' => $lockedLpj->only(array_keys(LpjSubmissionGate::NARRATIVE_FIELDS)),
                'findings' => $lockedLpj->currentVersionRecord?->findings->map(fn ($finding) => [
                    ...$finding->only(['id', 'code', 'severity', 'status', 'title', 'criteria', 'condition', 'cause', 'risk', 'recommendation', 'detail', 'due_at', 'resolved_at']),
                    'corrective_actions' => $finding->correctiveActions->map->only(['id', 'action', 'status', 'review_notes', 'submitted_at', 'reviewed_at'])->values()->all(),
                ])->values()->all() ?? [],
                'approval_history' => $lockedLpj->versions->flatMap(fn ($previousVersion) => $previousVersion->approvals->map(fn ($approval) => [
                    ...$approval->only(['id', 'level', 'decision', 'notes', 'signature_hash', 'decided_at']),
                    'lpj_version' => $previousVersion->version,
                ]))->values()->all(),
                'composer' => [
                    'template' => $precheck['template'],
                    'policy_version' => $precheck['policy_version'],
                    'completeness_percentage' => $precheck['percentage'],
                    'modules' => $precheck['modules'],
                    'draft_revision' => $lockedLpj->draft_revision,
                    'draft_updated_by' => $lockedLpj->draft_updated_by,
                    'draft_updated_at' => $lockedLpj->draft_updated_at?->toIso8601String(),
                ],
                'system_checks' => $systemChecks,
                'verification' => [
                    'policy_version' => $verificationPolicy['version'],
                    ...collect($verificationPolicy)->except('version')->all(),
                ],
                'total_amount' => $totalAmount,
                'submission_notes' => $data['notes'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ];
            $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $versionNumber = $lockedLpj->current_version + 1;

            $version = LpjVersion::create([
                'lpj_id' => $lockedLpj->id,
                'created_by' => $user->id,
                'submitted_by' => $user->id,
                'version' => $versionNumber,
                'status' => LpjStatus::Submitted,
                'snapshot' => $snapshot,
                'checksum' => hash('sha256', $encoded),
                'is_immutable' => true,
                'submitted_at' => now(),
            ]);

            $version->verifications()->create([
                'stage' => VerificationStage::System,
                'status' => VerificationStatus::Verified,
                'notes' => collect($systemChecks)->contains('result', 'WARNING')
                    ? 'Pre-check lulus dengan peringatan yang telah dicatat pada snapshot.'
                    : 'Seluruh pre-check sistem lulus.',
                'checklist' => $systemChecks,
                'evidence_ids' => $activeEvidence->pluck('id')->all(),
                'verified_at' => now(),
            ]);

            foreach (array_keys($verificationStages) as $stageValue) {
                $stage = VerificationStage::from($stageValue);
                if ($stage === VerificationStage::System) {
                    continue;
                }

                $version->verifications()->create([
                    'stage' => $stage,
                    'status' => VerificationStatus::Pending,
                ]);
            }

            $fromStatus = $lockedLpj->status->value;
            $lockedLpj->update([
                'status' => LpjStatus::InVerification,
                'current_version' => $versionNumber,
                'total_amount' => $totalAmount,
                'submitted_at' => now(),
                'approved_at' => null,
                'pb_verified_at' => null,
                'archived_at' => null,
            ]);

            $this->recordAuditEvent->handle($lockedLpj, 'lpj.submitted', $user, $fromStatus, LpjStatus::InVerification->value, [
                'version' => $versionNumber,
                'checksum' => $version->checksum,
            ]);
            $this->synchronizeWorkflowTasks->handle($lockedLpj, $user);

            return $version->load('verifications');
        });
    }
}
