<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\SmartTask;
use App\Models\User;

class SmartTaskPresentationService
{
    /** @var array<string, array{label: string, permission: string|null, action: string, completion: string, escalation: string, global: bool}> */
    private const DEFINITIONS = [
        'START_ACTIVITY' => ['label' => 'Mulai kegiatan', 'permission' => 'activities.manage', 'action' => 'Buka kegiatan', 'completion' => 'Kegiatan berstatus dalam pelaksanaan.', 'escalation' => 'Activity Owner dan koordinator unit', 'global' => false],
        'EVIDENCE_CAPTURE' => ['label' => 'Lengkapi bukti', 'permission' => 'evidence.manage', 'action' => 'Buka kegiatan', 'completion' => 'Bukti minimum tersedia dan kegiatan dapat diselesaikan.', 'escalation' => 'Activity Owner dan Finance PIC', 'global' => false],
        'COMPLETE_EVALUATION' => ['label' => 'Lengkapi evaluasi', 'permission' => 'activities.manage', 'action' => 'Buka evaluasi', 'completion' => 'Evaluasi output dan outcome diajukan untuk review.', 'escalation' => 'Activity Owner dan verifikator substantif', 'global' => false],
        'RESOLVE_EVALUATION_FINDING' => ['label' => 'Perbaiki evaluasi', 'permission' => 'activities.manage', 'action' => 'Buka evaluasi', 'completion' => 'Versi evaluasi perbaikan diajukan.', 'escalation' => 'Activity Owner dan verifikator substantif', 'global' => false],
        'REVIEW_EVALUATION' => ['label' => 'Review evaluasi', 'permission' => 'lpjs.verify.substantive', 'action' => 'Periksa evaluasi', 'completion' => 'Evaluasi diterima, diklarifikasi, atau diberi finding.', 'escalation' => 'Koordinator verifikasi substantif', 'global' => false],
        'COMPLETE_LPJ' => ['label' => 'Lengkapi LPJ', 'permission' => 'lpjs.submit', 'action' => 'Buka LPJ', 'completion' => 'Snapshot LPJ immutable berhasil diajukan.', 'escalation' => 'Activity Owner dan koordinator unit', 'global' => false],
        'PLAN_REVISION' => ['label' => 'Revisi planning', 'permission' => 'planning.manage', 'action' => 'Buka planning', 'completion' => 'Versi planning perbaikan diajukan.', 'escalation' => 'Pemilik planning dan reviewer', 'global' => false],
        'BUDGET_REVISION' => ['label' => 'Revisi RAB', 'permission' => 'budgets.manage', 'action' => 'Buka RAB', 'completion' => 'Versi RAB perbaikan diajukan.', 'escalation' => 'Finance PIC dan reviewer anggaran', 'global' => false],
        'RECONCILIATION_EXCEPTION' => ['label' => 'Selesaikan exception', 'permission' => 'finance.resolve-exception', 'action' => 'Buka rekonsiliasi', 'completion' => 'Exception diselesaikan atau memperoleh keputusan resmi.', 'escalation' => 'Finance PIC lalu approver exception', 'global' => false],
        'INTEGRATION_FAILURE' => ['label' => 'Pulihkan integrasi', 'permission' => 'integrations.manage', 'action' => 'Buka Integration Hub', 'completion' => 'Retry berhasil atau kegagalan permanen memperoleh resolusi.', 'escalation' => 'Integration owner dan administrator', 'global' => false],
        'DISPOSITION_RESPONSE' => ['label' => 'Jawab disposisi', 'permission' => 'dispositions.respond', 'action' => 'Buka disposisi', 'completion' => 'Bukti tanggapan diterima dan disposisi ditutup pemeriksa.', 'escalation' => 'Penerima, pengirim disposisi, lalu pimpinan unit', 'global' => false],
        'FINDING_RESPONSE' => ['label' => 'Tindak lanjuti finding', 'permission' => 'findings.respond', 'action' => 'Buka finding', 'completion' => 'CAPA dinilai efektif dan finding ditutup pihak berwenang.', 'escalation' => 'Owner CAPA dan koordinator verifikasi', 'global' => false],
        'VERIFY_LPJ_ADMINISTRATION' => ['label' => 'Verifikasi administrasi', 'permission' => 'lpjs.verify.administration', 'action' => 'Verifikasi LPJ', 'completion' => 'Gate administrasi memiliki keputusan yang sah.', 'escalation' => 'Koordinator verifikasi administrasi', 'global' => false],
        'VERIFY_LPJ_FINANCE' => ['label' => 'Verifikasi keuangan', 'permission' => 'lpjs.verify.finance', 'action' => 'Verifikasi LPJ', 'completion' => 'Gate keuangan memiliki keputusan yang sah.', 'escalation' => 'Koordinator verifikasi keuangan', 'global' => false],
        'VERIFY_LPJ_SUBSTANTIVE' => ['label' => 'Verifikasi substantif', 'permission' => 'lpjs.verify.substantive', 'action' => 'Verifikasi LPJ', 'completion' => 'Gate substantif memiliki keputusan yang sah.', 'escalation' => 'Koordinator verifikasi substantif', 'global' => false],
        'REVISE_LPJ' => ['label' => 'Revisi LPJ', 'permission' => 'lpjs.submit', 'action' => 'Perbaiki LPJ', 'completion' => 'Snapshot versi berikutnya berhasil diajukan.', 'escalation' => 'Activity Owner dan koordinator unit', 'global' => false],
        'APPROVE_LPJ' => ['label' => 'Keputusan LPJ', 'permission' => 'lpjs.approve', 'action' => 'Buka decision pack', 'completion' => 'Approver unit mencatat keputusan yang sah.', 'escalation' => 'Approver unit dan pimpinan organisasi', 'global' => false],
        'PB_VERIFY_LPJ' => ['label' => 'Verifikasi PB', 'permission' => 'lpjs.pb_verify', 'action' => 'Buka decision pack', 'completion' => 'PB menyetujui versi final dan Verified Pack diterbitkan.', 'escalation' => 'Koordinator PB dan pimpinan nasional', 'global' => true],
        'ARCHIVE_LPJ' => ['label' => 'Arsipkan LPJ', 'permission' => 'lpjs.archive', 'action' => 'Buka paket final', 'completion' => 'Paket final lolos pemeriksaan integritas dan masuk arsip.', 'escalation' => 'Administrator unit dan pemilik retensi', 'global' => false],
        'FOLLOW_UP' => ['label' => 'Tindak lanjut', 'permission' => null, 'action' => 'Buka sumber', 'completion' => 'Tindak lanjut pada objek sumber diselesaikan.', 'escalation' => 'Koordinator unit', 'global' => false],
    ];

    /** @return array{label: string, permission: string|null, action: string, completion: string, escalation: string, global: bool} */
    public function definition(string $type): array
    {
        return self::DEFINITIONS[$type] ?? self::DEFINITIONS['FOLLOW_UP'];
    }

    /** @return list<string> */
    public function unassignedTypesVisibleTo(User $user, bool $globalOnly = false): array
    {
        return collect(self::DEFINITIONS)
            ->filter(fn (array $definition): bool => $definition['permission'] !== null
                && (! $globalOnly || $definition['global'])
                && $user->can($definition['permission']))
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function lpjWorkflowTypes(): array
    {
        return [
            'VERIFY_LPJ_ADMINISTRATION',
            'VERIFY_LPJ_FINANCE',
            'VERIFY_LPJ_SUBSTANTIVE',
            'REVISE_LPJ',
            'APPROVE_LPJ',
            'PB_VERIFY_LPJ',
            'ARCHIVE_LPJ',
        ];
    }

    /** @return array<string, mixed> */
    public function present(SmartTask $task): array
    {
        $definition = $this->definition($task->type);
        $status = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
        $subjectType = $task->subject_type ?? ($task->activity_id ? 'ACTIVITY' : $this->subjectTypeFromMetadata($task));
        $subjectId = $task->subject_id ?? $task->activity_id ?? $this->subjectIdFromMetadata($task);

        return [
            'id' => $task->id,
            'type' => $task->type,
            'type_label' => $definition['label'],
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $status,
            'source' => $task->source,
            'created_at' => $task->created_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'sla' => $this->sla($task, $status),
            'completion_condition' => $task->completion_condition ?? $definition['completion'],
            'escalation_path' => $task->escalation_path ?? $definition['escalation'],
            'action_label' => $definition['action'],
            'action_href' => $this->actionHref($task),
            'is_actionable' => in_array($status, [TaskStatus::Open->value, TaskStatus::InProgress->value], true),
            'recipient' => [
                'kind' => $task->assignee_id ? 'USER' : 'CAPABILITY',
                'label' => $task->assignee?->name ?? $this->permissionLabel($task->recipient_permission ?? $definition['permission']),
            ],
            'unit' => $task->unit?->only(['id', 'code', 'name']),
            'object' => [
                'type' => $subjectType,
                'id' => $subjectId,
                'label' => $task->activity?->code ?? ($subjectId ? str($subjectId)->limit(18)->toString() : 'Sistem'),
                'title' => $task->activity?->title,
            ],
        ];
    }

    /** @return array{status: string, label: string, tone: string} */
    private function sla(SmartTask $task, string $status): array
    {
        if (in_array($status, [TaskStatus::Done->value, TaskStatus::Cancelled->value], true)) {
            return ['status' => 'COMPLETED', 'label' => 'Selesai', 'tone' => 'success'];
        }

        if ($task->due_at === null) {
            return ['status' => 'NO_DEADLINE', 'label' => 'Tanpa tenggat', 'tone' => 'neutral'];
        }

        if ($task->due_at->isPast()) {
            return ['status' => 'OVERDUE', 'label' => 'Lewat tenggat', 'tone' => 'danger'];
        }

        if ($task->due_at->isToday()) {
            return ['status' => 'DUE_TODAY', 'label' => 'Jatuh tempo hari ini', 'tone' => 'warning'];
        }

        if ($task->escalation_at?->isPast()) {
            return ['status' => 'AT_RISK', 'label' => 'Mendekati eskalasi', 'tone' => 'warning'];
        }

        return ['status' => 'ON_TRACK', 'label' => 'Dalam SLA', 'tone' => 'info'];
    }

    private function actionHref(SmartTask $task): string
    {
        $storedUrl = data_get($task->metadata, 'action_url');
        if (is_string($storedUrl) && str_starts_with($storedUrl, '/') && ! str_starts_with($storedUrl, '//')) {
            return $storedUrl;
        }

        if ($task->type === 'INTEGRATION_FAILURE') {
            return route('integrations.index', absolute: false);
        }

        if ($task->type === 'RECONCILIATION_EXCEPTION') {
            return route('finance.index', absolute: false);
        }

        if ($task->type === 'PLAN_REVISION') {
            return route('planning.index', ['focus' => data_get($task->metadata, 'plan_id')], false);
        }

        if ($task->type === 'BUDGET_REVISION') {
            return route('budgets.index', ['focus' => data_get($task->metadata, 'budget_id')], false);
        }

        if ($task->activity?->lpj !== null && in_array($task->type, [
            'COMPLETE_LPJ', 'DISPOSITION_RESPONSE', 'FINDING_RESPONSE',
            ...$this->lpjWorkflowTypes(),
        ], true)) {
            return route('lpjs.show', $task->activity->lpj, false).$this->lpjAnchor($task->type);
        }

        if ($task->activity_id !== null) {
            return route('activities.show', $task->activity_id, false);
        }

        return route('dashboard', absolute: false);
    }

    private function lpjAnchor(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'VERIFY_LPJ_') => '#lpj-verification',
            $type === 'FINDING_RESPONSE' => '#lpj-findings',
            $type === 'DISPOSITION_RESPONSE' => '#lpj-dispositions',
            in_array($type, ['APPROVE_LPJ', 'PB_VERIFY_LPJ'], true) => '#lpj-approval',
            default => '#lpj-composer',
        };
    }

    private function subjectTypeFromMetadata(SmartTask $task): ?string
    {
        return match (true) {
            data_get($task->metadata, 'lpj_id') !== null => 'LPJ',
            data_get($task->metadata, 'finding_id') !== null => 'FINDING',
            data_get($task->metadata, 'disposition_id') !== null => 'DISPOSITION',
            data_get($task->metadata, 'reconciliation_exception_id') !== null => 'RECONCILIATION_EXCEPTION',
            data_get($task->metadata, 'integration_run_id') !== null => 'INTEGRATION_RUN',
            data_get($task->metadata, 'plan_id') !== null => 'PLAN',
            data_get($task->metadata, 'budget_id') !== null => 'BUDGET',
            default => null,
        };
    }

    private function subjectIdFromMetadata(SmartTask $task): ?string
    {
        foreach (['lpj_id', 'finding_id', 'disposition_id', 'reconciliation_exception_id', 'integration_run_id', 'plan_id', 'budget_id'] as $key) {
            $value = data_get($task->metadata, $key);
            if (is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    private function permissionLabel(?string $permission): string
    {
        return match ($permission) {
            'lpjs.verify.administration' => 'Verifikator administrasi',
            'lpjs.verify.finance' => 'Verifikator keuangan',
            'lpjs.verify.substantive' => 'Verifikator substantif',
            'lpjs.approve' => 'Approver unit',
            'lpjs.pb_verify' => 'Verifikator PB',
            'lpjs.archive' => 'Administrator arsip',
            'finance.resolve-exception' => 'Finance PIC',
            'integrations.manage' => 'Pemilik integrasi',
            default => $permission ? str($permission)->replace(['.', '-'], ' ')->headline()->toString() : 'Tim unit',
        };
    }
}
