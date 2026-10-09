<?php

namespace App\Actions;

use App\Contracts\EvidenceScanner;
use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Models\Evidence;
use App\Support\EvidenceScanResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessEvidenceScanAction
{
    public function __construct(
        private EvidenceScanner $scanner,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(Evidence $evidence): Evidence
    {
        if ($evidence->scan_status !== EvidenceScanStatus::Uploading) {
            return $evidence;
        }

        $source = Storage::disk($evidence->disk)->readStream($evidence->path);
        if ($source === false) {
            throw new RuntimeException('Berkas karantina tidak dapat dibaca.');
        }

        $temporary = tmpfile();
        if ($temporary === false) {
            fclose($source);

            throw new RuntimeException('Berkas sementara untuk pemindaian tidak dapat dibuat.');
        }

        try {
            $hash = hash_init('sha256');
            $size = 0;
            $maximumSize = (int) config('evidence.max_size_kb') * 1024;

            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Berkas karantina gagal dibaca saat pemeriksaan integritas.');
                }

                $size += mb_strlen($chunk, '8bit');
                if ($size > $maximumSize) {
                    break;
                }
                hash_update($hash, $chunk);

                if (fwrite($temporary, $chunk) === false) {
                    throw new RuntimeException('Berkas sementara gagal ditulis.');
                }
            }

            $checksum = hash_final($hash);
            $result = match (true) {
                $size > $maximumSize => new EvidenceScanResult(
                    clean: false,
                    detectedMimeType: $evidence->mime_type,
                    engine: 'size-policy',
                    threat: 'FILE_TOO_LARGE',
                    notes: 'Ukuran berkas melampaui batas kebijakan evidence.',
                ),
                $checksum !== $evidence->sha256 || $size !== $evidence->size => new EvidenceScanResult(
                    clean: false,
                    detectedMimeType: $evidence->mime_type,
                    engine: 'integrity',
                    threat: 'INTEGRITY_MISMATCH',
                    notes: 'Checksum atau ukuran berkas berubah setelah unggahan.',
                ),
                default => $this->scanner->scan((string) stream_get_meta_data($temporary)['uri'], $evidence->original_name),
            };
        } finally {
            fclose($source);
            fclose($temporary);
        }

        return $this->recordResult($evidence, $result);
    }

    public function markInfrastructureFailure(Evidence $evidence): Evidence
    {
        return $this->recordResult($evidence, new EvidenceScanResult(
            clean: false,
            detectedMimeType: $evidence->mime_type,
            engine: 'infrastructure',
            threat: 'SCANNER_UNAVAILABLE',
            notes: 'Pemindaian tidak dapat diselesaikan setelah seluruh percobaan.',
        ));
    }

    private function recordResult(Evidence $evidence, EvidenceScanResult $result): Evidence
    {
        return DB::transaction(function () use ($evidence, $result): Evidence {
            $lockedEvidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            if ($lockedEvidence->scan_status !== EvidenceScanStatus::Uploading) {
                return $lockedEvidence;
            }

            $toStatus = $result->clean
                ? EvidenceScanStatus::Registered
                : EvidenceScanStatus::ScanFailed;

            $lockedEvidence->update([
                'scan_status' => $toStatus,
                'detected_mime_type' => $result->detectedMimeType,
                'scan_engine' => mb_strtoupper($result->engine),
                'scan_notes' => $result->notes,
                'scanned_at' => now(),
                'linked_at' => $result->clean ? now() : null,
            ]);

            if ($result->clean && $lockedEvidence->supersedes_id !== null) {
                Evidence::query()
                    ->whereKey($lockedEvidence->supersedes_id)
                    ->where(function ($query): void {
                        $query->where('review_status', EvidenceReviewStatus::Rejected)
                            ->orWhere('scan_status', EvidenceScanStatus::ScanFailed);
                    })
                    ->update(['review_status' => EvidenceReviewStatus::Superseded]);
            }

            $this->recordAuditEvent->handle(
                $lockedEvidence,
                $result->clean ? 'evidence.scan_passed' : 'evidence.scan_failed',
                fromStatus: EvidenceScanStatus::Uploading->value,
                toStatus: $toStatus->value,
                payload: [
                    'engine' => $result->engine,
                    'detected_mime_type' => $result->detectedMimeType,
                    'threat' => $result->threat,
                ],
            );

            return $lockedEvidence->fresh(['uploader:id,name', 'reviewer:id,name']);
        }, attempts: 3);
    }
}
