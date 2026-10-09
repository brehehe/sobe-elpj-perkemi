<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $records = DB::table('lpj_versions as version')
            ->join('lpjs as lpj', 'lpj.id', '=', 'version.lpj_id')
            ->join('activities as activity', 'activity.id', '=', 'lpj.activity_id')
            ->leftJoin('units as unit', 'unit.id', '=', 'activity.unit_id')
            ->join('approvals as approval', function ($join): void {
                $join->on('approval.lpj_version_id', '=', 'version.id')
                    ->where('approval.level', 'PB')
                    ->where('approval.decision', 'APPROVED');
            })
            ->leftJoin('archives as archive', 'archive.lpj_version_id', '=', 'version.id')
            ->leftJoin('verified_packs as verified_pack', 'verified_pack.lpj_version_id', '=', 'version.id')
            ->whereIn('lpj.status', ['PB_VERIFIED', 'ARCHIVED'])
            ->whereNull('verified_pack.id')
            ->select([
                'version.id as lpj_version_id',
                'version.lpj_id',
                'version.version',
                'version.snapshot',
                'version.checksum as lpj_checksum',
                'activity.id as activity_id',
                'activity.code as activity_code',
                'activity.title as activity_title',
                'unit.id as unit_id',
                'unit.code as unit_code',
                'unit.name as unit_name',
                'approval.id as approval_id',
                'approval.decided_by',
                'approval.signature_hash',
                'approval.decided_at',
                'archive.id as archive_id',
                'archive.archive_number',
                'archive.archived_by',
                'archive.disk as archive_disk',
                'archive.path as archive_path',
                'archive.archived_at',
                'archive.retention_until',
            ])
            ->get();

        foreach ($records as $record) {
            $packId = (string) Str::uuid7();
            $publicToken = (string) Str::uuid7();
            $issuedAt = Carbon::parse($record->decided_at);
            $verificationNumber = 'VRF-'.$issuedAt->format('Ym').'-'.Str::upper(substr(str_replace('-', '', $packId), -10));
            $disk = (string) config('lpj.verified_pack_policy.disk', 'local');
            $snapshot = json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $archivePayload = $this->archivePayload($record->archive_disk, $record->archive_path);
            $package = [
                'schema' => 'smart-e-lpj.verified-pack',
                'schema_version' => 1,
                'verification_id' => $packId,
                'verification_number' => $verificationNumber,
                'issued_at' => $issuedAt->toIso8601String(),
                'policy_version' => 'lpj-verified-pack-v1',
                'lineage' => [
                    'activity_id' => $record->activity_id,
                    'lpj_id' => $record->lpj_id,
                    'lpj_version_id' => $record->lpj_version_id,
                    'approval_id' => $record->approval_id,
                    'legacy_archive_id' => $record->archive_id,
                ],
                'lpj' => [
                    'version' => $record->version,
                    'snapshot_checksum' => $record->lpj_checksum,
                    'snapshot' => $snapshot,
                ],
                'activity' => [
                    'id' => $record->activity_id,
                    'code' => $record->activity_code,
                    'title' => $record->activity_title,
                ],
                'unit' => [
                    'id' => $record->unit_id,
                    'code' => $record->unit_code,
                    'name' => $record->unit_name,
                ],
                'evidence_manifest' => $archivePayload['evidence_manifest'] ?? ($snapshot['evidence'] ?? []),
                'verifications' => $archivePayload['verifications'] ?? [],
                'approvals' => $archivePayload['approvals'] ?? [],
                'findings_and_capa' => $archivePayload['findings_and_capa'] ?? [],
                'dispositions' => $archivePayload['dispositions'] ?? [],
                'reconciliations' => $archivePayload['reconciliations'] ?? [],
                'audit_summary' => $archivePayload['audit_summary'] ?? [],
                'public_reference' => [
                    'type' => 'PUBLIC_TOKEN',
                    'token' => $publicToken,
                    'path' => '/verify/'.$publicToken,
                ],
                'integrity' => [
                    'algorithm' => 'SHA-256',
                    'lpj_checksum' => $record->lpj_checksum,
                    'approval_signature_hash' => $record->signature_hash,
                ],
            ];
            $content = json_encode($package, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $path = 'verified-packs/'.$verificationNumber.'.json';

            if (! Storage::disk($disk)->put($path, $content)) {
                throw new RuntimeException('Failed to backfill Verified Pack '.$verificationNumber.'.');
            }

            $now = now();
            $checksum = hash('sha256', $content);
            DB::table('verified_packs')->insert([
                'id' => $packId,
                'lpj_version_id' => $record->lpj_version_id,
                'approval_id' => $record->approval_id,
                'issued_by' => $record->archived_by ?: $record->decided_by,
                'verification_number' => $verificationNumber,
                'policy_version' => 'lpj-verified-pack-v1',
                'schema_version' => 1,
                'disk' => $disk,
                'path' => $path,
                'checksum' => $checksum,
                'lpj_checksum' => $record->lpj_checksum,
                'approval_signature_hash' => $record->signature_hash,
                'public_token' => $publicToken,
                'metadata' => json_encode([
                    'reference_type' => 'PUBLIC_TOKEN',
                    'reference_path' => '/verify/'.$publicToken,
                    'legacy_backfill' => true,
                    'source_archive_number' => $record->archive_number,
                ], JSON_THROW_ON_ERROR),
                'issued_at' => $issuedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($record->archive_id) {
                $retentionUntil = $record->retention_until
                    ? Carbon::parse($record->retention_until)
                    : Carbon::parse($record->archived_at)->addYears(10);
                DB::table('archives')->where('id', $record->archive_id)->update([
                    'verified_pack_id' => $packId,
                    'verified_pack_checksum' => $checksum,
                    'retention_policy_version' => 'lpj-archive-retention-v1',
                    'package_schema_version' => 1,
                    'integrity_verified_at' => $now,
                    'retention_until' => $retentionUntil,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $backfilledPacks = DB::table('verified_packs')
            ->whereLike('metadata', '%"legacy_backfill":true%')
            ->get(['id', 'disk', 'path']);

        foreach ($backfilledPacks as $pack) {
            DB::table('archives')->where('verified_pack_id', $pack->id)->update([
                'verified_pack_id' => null,
                'verified_pack_checksum' => null,
                'integrity_verified_at' => null,
            ]);
            Storage::disk($pack->disk)->delete($pack->path);
            DB::table('verified_packs')->where('id', $pack->id)->delete();
        }
    }

    /** @return array<string, mixed> */
    private function archivePayload(?string $disk, ?string $path): array
    {
        if (! $disk || ! $path || ! Storage::disk($disk)->exists($path)) {
            return [];
        }

        $payload = json_decode(Storage::disk($disk)->get($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : [];
    }
};
