<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Enums\LpjStatus;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewCorrectiveActionAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
    ) {}

    public function handle(Finding $finding, CorrectiveAction $correctiveAction, User $reviewer, string $decision, ?string $notes): Finding
    {
        return DB::transaction(function () use ($finding, $correctiveAction, $reviewer, $decision, $notes): Finding {
            $lockedFinding = Finding::query()->lockForUpdate()->findOrFail($finding->id);
            $lockedAction = CorrectiveAction::query()->lockForUpdate()->findOrFail($correctiveAction->id);
            if ($lockedAction->finding_id !== $lockedFinding->id || $lockedAction->status !== 'SUBMITTED') {
                throw ValidationException::withMessages(['decision' => 'CAPA ini tidak lagi menunggu pemeriksaan.']);
            }
            if ($lockedAction->submitted_by === $reviewer->id) {
                throw ValidationException::withMessages(['decision' => 'Pengaju CAPA tidak boleh memeriksa tindakannya sendiri.']);
            }

            $accepted = $decision === 'ACCEPTED';
            $lockedAction->update([
                'reviewed_by' => $reviewer->id,
                'status' => $decision,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $lockedFinding->update([
                'status' => $accepted ? FindingStatus::Resolved : FindingStatus::Open,
                'resolved_by' => $accepted ? $reviewer->id : null,
                'resolved_at' => $accepted ? now() : null,
            ]);

            $lpj = $lockedFinding->lpjVersion->lpj;
            if ($accepted && ! $lockedFinding->lpjVersion->findings()->whereIn('status', [FindingStatus::Open, FindingStatus::CapaSubmitted])->exists()) {
                $lpj->update(['status' => LpjStatus::InVerification]);
            }

            $this->recordAuditEvent->handle(
                $lockedFinding,
                $accepted ? 'finding.capa_accepted' : 'finding.capa_rejected',
                $reviewer,
                FindingStatus::CapaSubmitted->value,
                $lockedFinding->status->value,
                ['corrective_action_id' => $lockedAction->id, 'review_notes' => $notes],
            );
            $this->synchronizeWorkflowTasks->handle($lpj, $reviewer);

            return $lockedFinding->fresh();
        });
    }
}
