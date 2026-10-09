<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Models\VerifiedPack;
use App\Services\VerifiedPackIntegrity;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifiedPackDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(VerifiedPack $verifiedPack, VerifiedPackIntegrity $integrity, RecordAuditEventAction $audit): StreamedResponse
    {
        $this->authorize('view', $verifiedPack->lpjVersion->lpj);
        $result = $integrity->inspect($verifiedPack);
        $audit->handle($verifiedPack, 'verified_pack.downloaded', request()->user(), payload: [
            'verification_number' => $verifiedPack->verification_number,
            'integrity_status' => $result['status'],
        ], result: $result['status'] === 'VALID' ? 'SUCCEEDED' : 'FAILED');

        if ($result['status'] !== 'VALID') {
            throw new HttpException(409, 'Integritas Verified Pack tidak valid. Unduhan diblokir.');
        }

        return Storage::disk($verifiedPack->disk)->download($verifiedPack->path, $verifiedPack->verification_number.'.json', [
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
