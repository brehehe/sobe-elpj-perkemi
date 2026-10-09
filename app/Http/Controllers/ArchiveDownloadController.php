<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Models\Archive;
use App\Services\VerifiedPackIntegrity;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ArchiveDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Archive $archive, RecordAuditEventAction $audit, VerifiedPackIntegrity $verifiedPackIntegrity): StreamedResponse
    {
        $this->authorize('view', $archive->lpjVersion->lpj);
        $storedContent = Storage::disk($archive->disk)->exists($archive->path)
            ? Storage::disk($archive->disk)->get($archive->path)
            : null;
        $archiveChecksumValid = is_string($storedContent) && hash_equals($archive->checksum, hash('sha256', $storedContent));
        $verifiedPackIntegrityResult = $archive->verifiedPack
            ? $verifiedPackIntegrity->inspect($archive->verifiedPack)
            : ['status' => 'UNAVAILABLE'];
        $integrityValid = $archiveChecksumValid && $verifiedPackIntegrityResult['status'] === 'VALID';

        $audit->handle($archive, 'archive.accessed', request()->user(), payload: [
            'archive_number' => $archive->archive_number,
            'archive_checksum_valid' => $archiveChecksumValid,
            'verified_pack_integrity' => $verifiedPackIntegrityResult['status'],
        ], result: $integrityValid ? 'SUCCEEDED' : 'FAILED');

        if (! $integrityValid) {
            throw new HttpException(409, 'Integritas paket arsip tidak valid. Unduhan diblokir.');
        }

        return Storage::disk($archive->disk)->download($archive->path, $archive->archive_number.'.json', [
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
