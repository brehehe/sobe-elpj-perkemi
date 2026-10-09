<?php

namespace App\Actions;

use App\Enums\DispositionStatus;
use App\Enums\GovernancePolicyType;
use App\Enums\LpjStatus;
use App\Enums\ReconciliationExceptionStatus;
use App\Models\Archive;
use App\Models\AuditEvent;
use App\Models\Lpj;
use App\Models\User;
use App\Services\GovernancePolicyRegistry;
use App\Services\VerifiedPackIntegrity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArchiveLpjAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private VerifiedPackIntegrity $verifiedPackIntegrity,
        private SynchronizeLpjWorkflowTasksAction $synchronizeWorkflowTasks,
        private GovernancePolicyRegistry $policyRegistry,
    ) {}

    public function handle(Lpj $lpj, User $user): Archive
    {
        return DB::transaction(function () use ($lpj, $user): Archive {
            $lockedLpj = Lpj::query()->lockForUpdate()->findOrFail($lpj->id);
            if ($lockedLpj->status !== LpjStatus::PbVerified) {
                throw ValidationException::withMessages(['status' => 'Hanya LPJ terverifikasi PB yang dapat diarsipkan.']);
            }
            $version = $lockedLpj->currentVersionRecord()->with(['approvals', 'verifications', 'dispositions', 'findings.correctiveActions', 'verifiedPack.approval'])->firstOrFail();
            $activity = $lockedLpj->activity()->firstOrFail();
            $verifiedPack = $version->verifiedPack;
            if (! $verifiedPack) {
                throw ValidationException::withMessages(['verified_pack' => 'Verified Pack belum tersedia. Arsip tidak dapat diterbitkan.']);
            }
            $integrity = $this->verifiedPackIntegrity->inspect($verifiedPack);
            if ($integrity['status'] !== 'VALID') {
                throw ValidationException::withMessages(['verified_pack' => 'Integritas Verified Pack tidak valid. Arsip diblokir.']);
            }
            if ($version->dispositions->contains(fn ($disposition) => in_array($disposition->status, [DispositionStatus::Open, DispositionStatus::Acknowledged, DispositionStatus::InProgress, DispositionStatus::EvidenceSubmitted, DispositionStatus::Reopened], true))) {
                throw ValidationException::withMessages(['disposition' => 'Arsip ditahan sampai seluruh disposisi selesai atau dibatalkan.']);
            }
            if ($lockedLpj->activity()->whereHas('transactions.reconciliation.exceptions', fn ($query) => $query->whereIn('status', ReconciliationExceptionStatus::BLOCKING_VALUES))->exists()) {
                throw ValidationException::withMessages(['reconciliation' => 'Arsip ditahan karena exception rekonsiliasi masih terbuka.']);
            }
            $archiveNumber = 'ARS-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
            $retentionPolicy = $this->policyRegistry->get(GovernancePolicyType::Retention);
            $retentionYears = (int) ($retentionPolicy['retention_years'] ?? 10);
            $retentionUntil = now()->addYears($retentionYears);
            $auditIds = collect([$activity->id, $lockedLpj->id, $version->id])
                ->merge($version->findings->pluck('id'))
                ->merge($version->dispositions->pluck('id'));
            $package = [
                'schema' => 'smart-e-lpj.archive-package',
                'schema_version' => (int) ($retentionPolicy['schema_version'] ?? 1),
                'archive_number' => $archiveNumber,
                'retention_policy_version' => (string) $retentionPolicy['version'],
                'lpj_version_id' => $version->id,
                'snapshot_checksum' => $version->checksum,
                'snapshot' => $version->snapshot,
                'verifications' => $version->verifications->toArray(),
                'approvals' => $version->approvals->toArray(),
                'findings_and_capa' => $version->findings->toArray(),
                'dispositions' => $version->dispositions->toArray(),
                'evidence_manifest' => $version->snapshot['evidence'] ?? [],
                'verified_pack' => [
                    'id' => $verifiedPack->id,
                    'verification_number' => $verifiedPack->verification_number,
                    'approval_id' => $verifiedPack->approval_id,
                    'checksum' => $verifiedPack->checksum,
                    'policy_version' => $verifiedPack->policy_version,
                    'issued_at' => $verifiedPack->issued_at?->toIso8601String(),
                    'integrity' => $integrity,
                    'payload' => json_decode(Storage::disk($verifiedPack->disk)->get($verifiedPack->path), true, flags: JSON_THROW_ON_ERROR),
                ],
                'reconciliations' => $activity->transactions()
                    ->with(['budgetLine', 'reconciliation.exceptions'])
                    ->get()
                    ->map(fn ($transaction) => [
                        'transaction' => $transaction->only(['id', 'reference_number', 'amount', 'reconciliation_status']),
                        'budget_line' => $transaction->budgetLine?->only(['id', 'account_code', 'description', 'subtotal']),
                        'reconciliation' => $transaction->reconciliation?->toArray(),
                    ])->all(),
                'audit_summary' => AuditEvent::query()
                    ->whereIn('auditable_id', $auditIds)
                    ->orderBy('created_at')
                    ->get(['event_id', 'correlation_id', 'request_id', 'auditable_type', 'auditable_id', 'object_version', 'event_type', 'source_channel', 'authority', 'payload_version', 'from_status', 'to_status', 'result', 'evidence_references', 'created_at'])
                    ->toArray(),
                'retention_until' => $retentionUntil->toIso8601String(),
                'archived_at' => now()->toIso8601String(),
            ];
            $content = json_encode($package, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $path = 'archives/'.$archiveNumber.'.json';
            $disk = (string) ($retentionPolicy['disk'] ?? 'local');
            if (! Storage::disk($disk)->put($path, $content)) {
                throw ValidationException::withMessages(['archive' => 'Paket arsip gagal ditulis ke penyimpanan.']);
            }

            $archive = Archive::create([
                'lpj_version_id' => $version->id,
                'verified_pack_id' => $verifiedPack->id,
                'archived_by' => $user->id,
                'archive_number' => $archiveNumber,
                'disk' => $disk,
                'path' => $path,
                'checksum' => hash('sha256', $content),
                'verified_pack_checksum' => $verifiedPack->checksum,
                'public_token' => (string) Str::uuid7(),
                'retention_policy_version' => (string) $retentionPolicy['version'],
                'package_schema_version' => (int) ($retentionPolicy['schema_version'] ?? 1),
                'archived_at' => now(),
                'integrity_verified_at' => $integrity['checked_at'],
                'retention_until' => $retentionUntil,
            ]);
            $lockedLpj->update(['status' => LpjStatus::Archived, 'archived_at' => now()]);
            $this->recordAuditEvent->handle($lockedLpj, 'lpj.archived', $user, LpjStatus::PbVerified->value, LpjStatus::Archived->value, [
                'archive_number' => $archiveNumber,
                'checksum' => $archive->checksum,
                'verification_id' => $verifiedPack->id,
                'verified_pack_checksum' => $verifiedPack->checksum,
                'retention_policy_version' => $archive->retention_policy_version,
            ]);
            $this->synchronizeWorkflowTasks->handle($lockedLpj, $user);

            return $archive;
        });
    }
}
