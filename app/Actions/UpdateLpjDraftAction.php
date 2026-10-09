<?php

namespace App\Actions;

use App\Enums\LpjStatus;
use App\Models\Lpj;
use App\Models\User;
use App\Services\LpjSubmissionGate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLpjDraftAction
{
    public function __construct(
        private LpjSubmissionGate $submissionGate,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Lpj $lpj, User $user, array $data): Lpj
    {
        return DB::transaction(function () use ($lpj, $user, $data): Lpj {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            $allowed = [LpjStatus::Draft, LpjStatus::ReadyToSubmit, LpjStatus::Returned, LpjStatus::ClarificationRequired];

            if (! in_array($lockedLpj->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Draft LPJ tidak dapat diubah pada status ini.']);
            }
            if ((int) $data['draft_revision'] !== $lockedLpj->draft_revision) {
                throw ValidationException::withMessages(['draft_revision' => 'Draft telah diperbarui di sesi lain. Muat ulang halaman sebelum menyimpan kembali.']);
            }

            $fromStatus = $lockedLpj->status;
            $lockedLpj->fill(Arr::only($data, array_keys(LpjSubmissionGate::NARRATIVE_FIELDS)));
            $lockedLpj->forceFill([
                'draft_revision' => $lockedLpj->draft_revision + 1,
                'draft_updated_by' => $user->id,
                'draft_updated_at' => now(),
            ])->save();

            $readiness = $this->submissionGate->evaluate($lockedLpj);
            if (in_array($fromStatus, [LpjStatus::Draft, LpjStatus::ReadyToSubmit], true)) {
                $lockedLpj->update(['status' => $readiness['ready'] ? LpjStatus::ReadyToSubmit : LpjStatus::Draft]);
            }

            $this->recordAuditEvent->handle(
                $lockedLpj,
                'lpj.draft_updated',
                $user,
                $fromStatus->value,
                $lockedLpj->status->value,
                [
                    'draft_revision' => $lockedLpj->draft_revision,
                    'completeness_percentage' => $readiness['percentage'],
                    'updated_fields' => array_keys(LpjSubmissionGate::NARRATIVE_FIELDS),
                ],
            );

            return $lockedLpj->fresh(['draftUpdater:id,name']);
        });
    }
}
