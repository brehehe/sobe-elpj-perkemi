<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\VerifyLpjAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyLpj;
use App\Http\Resources\Api\V1\LpjResource;
use App\Models\Lpj;
use App\Services\LpjVerificationGate;

class LpjVerificationController extends Controller
{
    public function store(
        VerifyLpj $request,
        Lpj $lpj,
        VerifyLpjAction $verify,
        LpjVerificationGate $verificationGate,
    ): LpjResource {
        $verifiedLpj = $verify->handle($lpj, $request->user(), $request->validated());

        return (new LpjResource($verifiedLpj))->additional([
            'meta' => ['verification_workflow' => $verificationGate->evaluate($verifiedLpj, $request->user())],
        ]);
    }
}
