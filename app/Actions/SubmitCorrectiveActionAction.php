<?php

namespace App\Actions;

use App\Enums\FindingStatus;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubmitCorrectiveActionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Finding $finding, User $user, string $action, ?UploadedFile $evidence = null): CorrectiveAction
    {
        return DB::transaction(function () use ($finding, $user, $action, $evidence): CorrectiveAction {
            $path = $evidence?->store('corrective-actions');
            $correctiveAction = CorrectiveAction::create([
                'finding_id' => $finding->id,
                'submitted_by' => $user->id,
                'action' => $action,
                'evidence_path' => $path,
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
            ]);
            $finding->update(['status' => FindingStatus::CapaSubmitted]);
            $this->recordAuditEvent->handle($finding, 'finding.capa_submitted', $user, FindingStatus::Open->value, FindingStatus::CapaSubmitted->value);

            return $correctiveAction;
        });
    }
}
