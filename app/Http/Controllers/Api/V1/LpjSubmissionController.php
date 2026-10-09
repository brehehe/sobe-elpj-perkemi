<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitLpjAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitLpj;
use App\Http\Resources\Api\V1\LpjVersionResource;
use App\Models\Lpj;

class LpjSubmissionController extends Controller
{
    public function store(SubmitLpj $request, Lpj $lpj, SubmitLpjAction $submit): LpjVersionResource
    {
        return new LpjVersionResource($submit->handle($lpj, $request->user(), $request->validated()));
    }
}
