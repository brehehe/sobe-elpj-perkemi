<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OpenFindingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinding;
use App\Http\Resources\Api\V1\FindingResource;
use App\Models\Lpj;

class FindingController extends Controller
{
    public function store(StoreFinding $request, Lpj $lpj, OpenFindingAction $open): FindingResource
    {
        return new FindingResource($open->handle($lpj, $request->user(), $request->validated()));
    }
}
