<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordAuditEventAction;
use App\Http\Controllers\Controller;
use App\Models\VerifiedPack;
use App\Services\VerifiedPackIntegrity;
use Illuminate\Http\JsonResponse;

class PublicVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $token, VerifiedPackIntegrity $integrity, RecordAuditEventAction $audit): JsonResponse
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

        return response()->json(['data' => $record]);
    }
}
