<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveLpjAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLpj;
use App\Models\Lpj;
use App\Services\LpjApprovalGate;
use Illuminate\Http\JsonResponse;

class LpjApprovalController extends Controller
{
    public function store(ApproveLpj $request, Lpj $lpj, ApproveLpjAction $approve, LpjApprovalGate $approvalGate): JsonResponse
    {
        $approval = $approve->handle($lpj, $request->user(), $request->validated());

        return response()->json([
            'data' => $approval,
            'meta' => ['approval_workflow' => $approvalGate->evaluate($lpj->fresh(), $request->user())],
        ], 201);
    }
}
