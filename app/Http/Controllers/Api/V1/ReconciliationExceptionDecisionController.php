<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DecideReconciliationExceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideReconciliationException;
use App\Http\Resources\Api\V1\ReconciliationExceptionResource;
use App\Models\ReconciliationException;

class ReconciliationExceptionDecisionController extends Controller
{
    public function store(DecideReconciliationException $request, ReconciliationException $reconciliationException, DecideReconciliationExceptionAction $decide): ReconciliationExceptionResource
    {
        return new ReconciliationExceptionResource(
            $decide->handle($reconciliationException, $request->user(), $request->validated()),
        );
    }
}
