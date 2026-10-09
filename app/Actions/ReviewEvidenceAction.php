<?php

namespace App\Actions;

use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewEvidenceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Evidence $evidence, User $user, EvidenceReviewStatus $status, ?string $notes): Evidence
    {
        return DB::transaction(function () use ($evidence, $user, $status, $notes): Evidence {
            $lockedEvidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);

            if ($lockedEvidence->review_status !== EvidenceReviewStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Hanya bukti berstatus pending yang dapat diperiksa.']);
            }
            if ($lockedEvidence->scan_status !== EvidenceScanStatus::Registered) {
                throw ValidationException::withMessages(['status' => 'Bukti belum lolos pemeriksaan berkas.']);
            }
            if ($lockedEvidence->uploaded_by === $user->id) {
                throw ValidationException::withMessages(['reviewer' => 'Pengunggah tidak boleh memeriksa buktinya sendiri.']);
            }

            $lockedEvidence->update([
                'reviewed_by' => $user->id,
                'review_status' => $status,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $this->recordAuditEvent->handle(
                $lockedEvidence,
                'evidence.reviewed',
                $user,
                EvidenceReviewStatus::Pending->value,
                $status->value,
                ['activity_id' => $lockedEvidence->activity_id, 'notes' => $notes],
            );

            return $lockedEvidence->fresh(['uploader:id,name', 'reviewer:id,name']);
        }, attempts: 3);
    }
}
