<?php

namespace App\Services;

use App\Contracts\EvidenceScanner;
use App\Support\EvidenceScanResult;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClamAvEvidenceScanner implements EvidenceScanner
{
    public function __construct(private SignatureEvidenceScanner $signatureScanner) {}

    public function scan(string $absolutePath, string $originalName): EvidenceScanResult
    {
        $baseline = $this->signatureScanner->scan($absolutePath, $originalName);
        if (! $baseline->clean) {
            return $baseline;
        }

        $process = new Process([
            (string) config('evidence.clamav.binary'),
            '--no-summary',
            $absolutePath,
        ]);
        $process->setTimeout((float) config('evidence.clamav.timeout_seconds'));
        $process->run();

        if ($process->getExitCode() === 0) {
            return new EvidenceScanResult(
                clean: true,
                detectedMimeType: $baseline->detectedMimeType,
                engine: 'clamav',
                notes: 'Pemindaian antivirus selesai tanpa temuan.',
            );
        }

        if ($process->getExitCode() === 1) {
            return new EvidenceScanResult(
                clean: false,
                detectedMimeType: $baseline->detectedMimeType,
                engine: 'clamav',
                threat: 'MALWARE_DETECTED',
                notes: 'Antivirus mendeteksi ancaman dan berkas tetap dikarantina.',
            );
        }

        throw new RuntimeException('Layanan antivirus tidak dapat menyelesaikan pemindaian.');
    }
}
