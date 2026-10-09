<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateLpjDraftAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLpjDraft;
use App\Http\Resources\Api\V1\LpjResource;
use App\Models\Lpj;

class LpjDraftController extends Controller
{
    public function update(UpdateLpjDraft $request, Lpj $lpj, UpdateLpjDraftAction $update): LpjResource
    {
        return new LpjResource($update->handle($lpj, $request->user(), $request->validated()));
    }
}
