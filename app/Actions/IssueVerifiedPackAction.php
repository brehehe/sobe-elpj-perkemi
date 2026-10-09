<?php

namespace App\Actions;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\Approval;
use App\Models\AuditEvent;
use App\Models\LpjVersion;
use App\Models\User;
use App\Models\VerifiedPack;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueVerifiedPackAction
{
    public function handle(LpjVersion $version, Approval $approval, User $issuer): VerifiedPack
    {
        if ($approval->lpj_version_id !== $version->id
            || $approval->level !== ApprovalLevel::Pb
            || $approval->decision !== ApprovalDecision::Approved) {
            throw ValidationException::withMessages([
                'verified_pack' => 'Verified Pack hanya dapat diterbitkan dari persetujuan PB yang valid untuk versi aktif.',
            ]);
        }

        if ($version->verifiedPack()->exists()) {
            throw ValidationException::withMessages([
                'verified_pack' => 'Verified Pack untuk versi LPJ ini sudah diterbitkan.',
            ]);
        }

        $version->loadMissing([
            'lpj.activity.unit',
            'lpj.activity.transactions.budgetLine',
            'lpj.activity.transactions.reconciliation.exceptions',
            'approvals.decisionMaker:id,name',
            'verifications.verifier:id,name',
            'findings.correctiveActions',
            'dispositions',
        ]);

        $packId = (string) Str::uuid7();
        $publicToken = (string) Str::uuid7();
        $verificationNumber = 'VRF-'.now()->format('Ym').'-'.Str::upper(substr(str_replace('-', '', $packId), -10));
        $issuedAt = now();
        $disk = (string) config('lpj.verified_pack_policy.disk', 'local');
        $schemaVersion = (int) config('lpj.verified_pack_policy.schema_version', 1);
        $policyVersion = (string) config('lpj.verified_pack_policy.version');
        $activity = $version->lpj->activity;
        $auditIds = collect([$activity->id, $version->lpj_id, $version->id])
            ->merge($version->findings->pluck('id'))
            ->merge($version->dispositions->pluck('id'));

        $package = [
            'schema' => 'smart-e-lpj.verified-pack',
            'schema_version' => $schemaVersion,
            'verification_id' => $packId,
            'verification_number' => $verificationNumber,
            'issued_at' => $issuedAt->toIso8601String(),
            'policy_version' => $policyVersion,
            'lineage' => [
                'activity_id' => $activity->id,
                'lpj_id' => $version->lpj_id,
                'lpj_version_id' => $version->id,
                'approval_id' => $approval->id,
            ],
            'lpj' => [
                'version' => $version->version,
                'snapshot_checksum' => $version->checksum,
                'snapshot' => $version->snapshot,
            ],
            'activity' => $activity->only(['id', 'code', 'title', 'unit_id', 'mandate_id', 'plan_id', 'plan_version', 'budget_id', 'budget_version']),
            'unit' => $activity->unit?->only(['id', 'code', 'name']),
            'evidence_manifest' => $version->snapshot['evidence'] ?? [],
            'verifications' => $version->verifications->toArray(),
            'approvals' => $version->approvals->toArray(),
            'findings_and_capa' => $version->findings->toArray(),
            'dispositions' => $version->dispositions->toArray(),
            'reconciliations' => $activity->transactions->map(fn ($transaction) => [
                'transaction' => $transaction->only(['id', 'reference_number', 'amount', 'reconciliation_status']),
                'budget_line' => $transaction->budgetLine?->only(['id', 'account_code', 'description', 'subtotal']),
                'reconciliation' => $transaction->reconciliation?->toArray(),
            ])->all(),
            'audit_summary' => AuditEvent::query()
                ->whereIn('auditable_id', $auditIds)
                ->orderBy('created_at')
                ->get(['event_id', 'correlation_id', 'request_id', 'auditable_type', 'auditable_id', 'object_version', 'event_type', 'source_channel', 'authority', 'payload_version', 'from_status', 'to_status', 'result', 'evidence_references', 'created_at'])
                ->toArray(),
            'public_reference' => [
                'type' => 'PUBLIC_TOKEN',
                'token' => $publicToken,
                'path' => '/verify/'.$publicToken,
            ],
            'integrity' => [
                'algorithm' => 'SHA-256',
                'lpj_checksum' => $version->checksum,
                'approval_signature_hash' => $approval->signature_hash,
            ],
        ];
        $content = json_encode($package, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $path = 'verified-packs/'.$verificationNumber.'.json';

        if (! Storage::disk($disk)->put($path, $content)) {
            throw ValidationException::withMessages([
                'verified_pack' => 'Verified Pack gagal ditulis ke penyimpanan. Status PB belum diubah.',
            ]);
        }

        return VerifiedPack::create([
            'id' => $packId,
            'lpj_version_id' => $version->id,
            'approval_id' => $approval->id,
            'issued_by' => $issuer->id,
            'verification_number' => $verificationNumber,
            'policy_version' => $policyVersion,
            'schema_version' => $schemaVersion,
            'disk' => $disk,
            'path' => $path,
            'checksum' => hash('sha256', $content),
            'lpj_checksum' => $version->checksum,
            'approval_signature_hash' => $approval->signature_hash,
            'public_token' => $publicToken,
            'metadata' => [
                'reference_type' => 'PUBLIC_TOKEN',
                'reference_path' => '/verify/'.$publicToken,
                'evidence_count' => count($package['evidence_manifest']),
                'audit_event_count' => count($package['audit_summary']),
            ],
            'issued_at' => $issuedAt,
        ]);
    }
}
