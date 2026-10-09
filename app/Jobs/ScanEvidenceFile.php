<?php

namespace App\Jobs;

use App\Actions\ProcessEvidenceScanAction;
use App\Enums\EvidenceScanStatus;
use App\Models\Evidence;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScanEvidenceFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public string $evidenceId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function uniqueId(): string
    {
        return $this->evidenceId;
    }

    /**
     * Execute the job.
     */
    public function handle(ProcessEvidenceScanAction $process): void
    {
        $evidence = Evidence::query()->find($this->evidenceId);
        if ($evidence === null || $evidence->scan_status !== EvidenceScanStatus::Uploading) {
            return;
        }

        try {
            $process->handle($evidence);
        } catch (Throwable $throwable) {
            if ($this->attempts() >= $this->tries) {
                $process->markInfrastructureFailure($evidence);
            }

            throw $throwable;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Evidence::query()
            ->whereKey($this->evidenceId)
            ->where('scan_status', EvidenceScanStatus::Uploading)
            ->update([
                'scan_status' => EvidenceScanStatus::ScanFailed,
                'scan_engine' => 'INFRASTRUCTURE',
                'scan_notes' => 'Pemindaian berhenti sebelum hasil dapat dicatat.',
                'scanned_at' => now(),
            ]);
    }
}
