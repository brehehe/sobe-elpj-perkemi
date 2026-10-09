<?php

namespace App\Actions;

use App\Enums\EvidenceReviewStatus;
use App\Enums\EvidenceScanStatus;
use App\Jobs\ScanEvidenceFile;
use App\Models\Activity;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreEvidenceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array<string, mixed> $data */
    public function handle(Activity $activity, User $user, UploadedFile $file, array $data): Evidence
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        if ($checksum === false) {
            throw new \RuntimeException('Checksum bukti gagal dihitung.');
        }
        $disk = (string) config('evidence.disk');
        $path = $file->store('evidence/'.$activity->id, $disk);
        if ($path === false) {
            throw new \RuntimeException('Berkas bukti gagal disimpan.');
        }

        try {
            $evidence = DB::transaction(function () use ($activity, $user, $file, $data, $checksum, $path, $disk): Evidence {
                $superseded = isset($data['supersedes_id'])
                    ? Evidence::query()->lockForUpdate()->findOrFail($data['supersedes_id'])
                    : null;
                if ($superseded !== null && $superseded->activity_id !== $activity->id) {
                    throw ValidationException::withMessages(['supersedes_id' => 'Bukti yang digantikan harus berasal dari kegiatan yang sama.']);
                }
                if ($superseded !== null
                    && $superseded->scan_status !== EvidenceScanStatus::ScanFailed
                    && $superseded->review_status !== EvidenceReviewStatus::Rejected) {
                    throw ValidationException::withMessages(['supersedes_id' => 'Hanya bukti yang ditolak atau gagal scan yang dapat diganti.']);
                }
                if ($superseded !== null && $superseded->type !== $data['type']) {
                    throw ValidationException::withMessages(['type' => 'Jenis bukti pengganti harus sama dengan versi sebelumnya.']);
                }
                if ($superseded?->revisions()->exists()) {
                    throw ValidationException::withMessages(['supersedes_id' => 'Pilih versi bukti paling baru untuk membuat penggantian.']);
                }

                $evidence = Evidence::create([
                    'activity_id' => $activity->id,
                    'supersedes_id' => $superseded?->id,
                    'uploaded_by' => $user->id,
                    'type' => $data['type'],
                    'version' => ($superseded?->version ?? 0) + 1,
                    'title' => $data['title'],
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                    'sha256' => $checksum,
                    'scan_status' => EvidenceScanStatus::Uploading,
                    'review_status' => EvidenceReviewStatus::Pending,
                    'access_classification' => $data['access_classification'] ?? 'INTERNAL',
                    'captured_at' => $data['captured_at'] ?? null,
                ]);

                $this->recordAuditEvent->handle($evidence, 'evidence.uploaded', $user, toStatus: EvidenceScanStatus::Uploading->value, payload: [
                    'activity_id' => $activity->id,
                    'sha256' => $evidence->sha256,
                    'disk' => $disk,
                ]);

                return $evidence;
            }, attempts: 3);
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }

        ScanEvidenceFile::dispatch($evidence->id)->afterCommit();

        return $evidence->refresh();
    }
}
