<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Models\VerifiedPack;
use App\Services\VerifiedPackIntegrity;
use Inertia\Inertia;
use Inertia\Response;

class PublicVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $token, VerifiedPackIntegrity $integrity, RecordAuditEventAction $audit): Response
    {
        abort_unless(config('lpj.verified_pack_policy.public_verification', true), 404);

        $verifiedPack = VerifiedPack::query()
            ->where('public_token', $token)
            ->firstOrFail();
        $record = $integrity->publicRecord($verifiedPack);
        $audit->handle($verifiedPack, 'verified_pack.public_checked', payload: [
            'verification_number' => $verifiedPack->verification_number,
            'integrity_status' => $record['status'],
            'checks' => $record['checks'],
        ], result: $record['status'] === 'VALID' ? 'SUCCEEDED' : 'FAILED');

        return Inertia::render('public/verification', [
            'verification' => $record,
        ]);
    }
}
