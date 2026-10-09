<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ResolveReconciliationExceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveReconciliationException;
use App\Http\Resources\Api\V1\ReconciliationExceptionResource;
use App\Models\ReconciliationException;

class ReconciliationExceptionController extends Controller
{
    public function update(ResolveReconciliationException $request, ReconciliationException $reconciliationException, ResolveReconciliationExceptionAction $resolve): ReconciliationExceptionResource
    {
        return new ReconciliationExceptionResource(
            $resolve->handle($reconciliationException, $request->user(), $request->validated()),
        );
    }
}
