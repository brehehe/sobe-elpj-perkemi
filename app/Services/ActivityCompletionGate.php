<?php

namespace App\Services;

use App\Enums\ActivityAgendaStatus;
use App\Enums\ActivityExecutionStatus;
use App\Enums\ActivityExecutionType;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Models\Activity;

class ActivityCompletionGate
{
    /**
     * @return array{ready: bool, policy_version: string, checks: array<int, array{key: string, label: string, ready: bool, detail: string}>, errors: array<string, string>}
     */
    public function evaluate(Activity $activity): array
    {
        $activity->loadMissing(['agendaItems', 'participants', 'executionRecords', 'evidence']);

        $supersededEvidenceIds = $activity->evidence->pluck('supersedes_id')->filter()->all();
        $allowedEvidenceTypes = config('activity.completion_policy.operational_evidence_types', []);
        $minimumEvidence = (int) config('activity.completion_policy.minimum_operational_evidence', 1);
        $activeOperationalEvidence = $activity->evidence
            ->reject(fn ($evidence): bool => in_array($evidence->id, $supersededEvidenceIds, true))
            ->filter(fn ($evidence): bool => in_array($evidence->type, $allowedEvidenceTypes, true))
            ->filter(fn ($evidence): bool => $evidence->scan_status === EvidenceScanStatus::Registered)
            ->reject(fn ($evidence): bool => in_array($evidence->review_status, [EvidenceReviewStatus::Rejected, EvidenceReviewStatus::Superseded], true));

        $completedAgendaCount = $activity->agendaItems->where('status', ActivityAgendaStatus::Completed)->count();
        $unfinishedAgendaCount = $activity->agendaItems
            ->whereIn('status', [ActivityAgendaStatus::Planned, ActivityAgendaStatus::InProgress])
            ->count();
        $openIssueCount = $activity->executionRecords
            ->whereIn('type', [ActivityExecutionType::Incident, ActivityExecutionType::Change])
            ->where('status', ActivityExecutionStatus::Open)
            ->count();
        $attendedCount = $activity->participants->where('attendance_status', 'ATTENDED')->count();

        $checks = [
            [
                'key' => 'schedule',
                'label' => 'Tanggal pelaksanaan telah berakhir',
                'ready' => ! $activity->ends_at->isFuture(),
                'detail' => $activity->ends_at->isFuture()
                    ? 'Kegiatan baru dapat diselesaikan setelah jadwal berakhir.'
                    : 'Rentang waktu pelaksanaan sudah terpenuhi.',
            ],
            [
                'key' => 'agenda',
                'label' => 'Seluruh agenda telah dituntaskan',
                'ready' => $completedAgendaCount > 0 && $unfinishedAgendaCount === 0,
                'detail' => $activity->agendaItems->isEmpty()
                    ? 'Tambahkan minimal satu agenda.'
                    : $unfinishedAgendaCount.' agenda masih aktif; '.$completedAgendaCount.' agenda selesai.',
            ],
            [
                'key' => 'attendance',
                'label' => 'Kehadiran peserta telah tercatat',
                'ready' => $attendedCount > 0,
                'detail' => $attendedCount.' peserta berstatus hadir.',
            ],
            [
                'key' => 'issues',
                'label' => 'Tidak ada insiden atau perubahan terbuka',
                'ready' => $openIssueCount === 0,
                'detail' => $openIssueCount === 0 ? 'Semua issue telah ditutup.' : $openIssueCount.' issue masih terbuka.',
            ],
            [
                'key' => 'evidence',
                'label' => 'Bukti operasional minimum tersedia',
                'ready' => $activeOperationalEvidence->count() >= $minimumEvidence,
                'detail' => $activeOperationalEvidence->count().' dari minimum '.$minimumEvidence.' bukti aktif memenuhi kebijakan.',
            ],
        ];
        $errors = collect($checks)
            ->reject(fn (array $check): bool => $check['ready'])
            ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['detail']])
            ->all();

        return [
            'ready' => $errors === [],
            'policy_version' => (string) config('activity.completion_policy.version'),
            'checks' => $checks,
            'errors' => $errors,
        ];
    }
}
