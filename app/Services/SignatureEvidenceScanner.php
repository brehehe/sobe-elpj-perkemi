<?php

namespace App\Services;

use App\Contracts\EvidenceScanner;
use App\Support\EvidenceScanResult;
use finfo;
use RuntimeException;

class SignatureEvidenceScanner implements EvidenceScanner
{
    public function scan(string $absolutePath, string $originalName): EvidenceScanResult
    {
        $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        if ($detectedMimeType === false) {
            throw new RuntimeException('Tipe berkas tidak dapat dideteksi.');
        }

        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedMimeTypes = config("evidence.allowed_extensions.{$extension}");
        if (! is_array($allowedMimeTypes) || ! in_array($detectedMimeType, $allowedMimeTypes, true)) {
            return new EvidenceScanResult(
                clean: false,
                detectedMimeType: $detectedMimeType,
                engine: 'signature',
                threat: 'TYPE_MISMATCH',
                notes: 'Isi berkas tidak sesuai dengan ekstensi yang diizinkan.',
            );
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException('Berkas tidak dapat dibaca oleh pemindai.');
        }

        $eicarSignature = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        if (str_contains($contents, $eicarSignature)) {
            return new EvidenceScanResult(
                clean: false,
                detectedMimeType: $detectedMimeType,
                engine: 'signature',
                threat: 'EICAR_TEST_SIGNATURE',
                notes: 'Berkas terdeteksi sebagai konten berbahaya dan tetap dikarantina.',
            );
        }

        return new EvidenceScanResult(
            clean: true,
            detectedMimeType: $detectedMimeType,
            engine: 'signature',
            notes: 'Validasi tipe dan signature lokal selesai.',
        );
    }
}
