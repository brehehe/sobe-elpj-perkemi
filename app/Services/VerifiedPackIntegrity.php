<?php

namespace App\Services;

use App\Models\VerifiedPack;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class VerifiedPackIntegrity
{
    /**
     * @return array<string, mixed>
     */
    public function publicRecord(VerifiedPack $verifiedPack): array
    {
        $verifiedPack->loadMissing(['lpjVersion.lpj.activity.unit', 'approval']);
        $integrity = $this->inspect($verifiedPack);
        $activity = $verifiedPack->lpjVersion->lpj->activity;

        return [
            'status' => $integrity['status'],
            'verification_id' => $verifiedPack->id,
            'verification_number' => $verifiedPack->verification_number,
            'approval_id' => $verifiedPack->approval_id,
            'issued_at' => $verifiedPack->issued_at?->toIso8601String(),
            'policy_version' => $verifiedPack->policy_version,
            'authority_reference' => $verifiedPack->approval->authority_reference,
            'checksum' => $verifiedPack->checksum,
            'algorithm' => 'SHA-256',
            'checked_at' => $integrity['checked_at'],
            'checks' => $integrity['checks'],
            'lpj' => [
                'code' => $activity->code,
                'title' => $activity->title,
                'version' => $verifiedPack->lpjVersion->version,
                'unit' => $activity->unit?->only(['code', 'name']),
            ],
        ];
    }

    /**
     * @return array{status: string, checks: array<string, bool>, checked_at: string}
     */
    public function inspect(VerifiedPack $verifiedPack): array
    {
        $verifiedPack->loadMissing(['lpjVersion', 'approval']);
        $checkedAt = now()->toIso8601String();

        try {
            if (! Storage::disk($verifiedPack->disk)->exists($verifiedPack->path)) {
                return $this->unavailable($checkedAt);
            }

            $content = Storage::disk($verifiedPack->disk)->get($verifiedPack->path);
            $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [
                'status' => 'INVALID',
                'checks' => [
                    'pack_file_available' => true,
                    'pack_payload_readable' => false,
                    'pack_checksum_valid' => false,
                    'lpj_snapshot_valid' => false,
                    'approval_signature_valid' => false,
                    'lineage_valid' => false,
                ],
                'checked_at' => $checkedAt,
            ];
        } catch (Throwable) {
            return $this->unavailable($checkedAt);
        }

        $snapshotContent = json_encode($verifiedPack->lpjVersion->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checks = [
            'pack_file_available' => true,
            'pack_payload_readable' => true,
            'pack_checksum_valid' => hash_equals($verifiedPack->checksum, hash('sha256', $content)),
            'lpj_snapshot_valid' => hash_equals($verifiedPack->lpj_checksum, hash('sha256', $snapshotContent))
                && hash_equals($verifiedPack->lpj_checksum, $verifiedPack->lpjVersion->checksum),
            'approval_signature_valid' => hash_equals($verifiedPack->approval_signature_hash, $verifiedPack->approval->signature_hash),
            'lineage_valid' => ($payload['verification_id'] ?? null) === $verifiedPack->id
                && ($payload['lineage']['lpj_version_id'] ?? null) === $verifiedPack->lpj_version_id
                && ($payload['lineage']['approval_id'] ?? null) === $verifiedPack->approval_id
                && ($payload['integrity']['lpj_checksum'] ?? null) === $verifiedPack->lpj_checksum
                && ($payload['integrity']['approval_signature_hash'] ?? null) === $verifiedPack->approval_signature_hash,
        ];

        return [
            'status' => collect($checks)->every(fn (bool $passed): bool => $passed) ? 'VALID' : 'INVALID',
            'checks' => $checks,
            'checked_at' => $checkedAt,
        ];
    }

    /** @return array{status: string, checks: array<string, bool>, checked_at: string} */
    private function unavailable(string $checkedAt): array
    {
        return [
            'status' => 'UNAVAILABLE',
            'checks' => [
                'pack_file_available' => false,
                'pack_payload_readable' => false,
                'pack_checksum_valid' => false,
                'lpj_snapshot_valid' => false,
                'approval_signature_valid' => false,
                'lineage_valid' => false,
            ],
            'checked_at' => $checkedAt,
        ];
    }
}
