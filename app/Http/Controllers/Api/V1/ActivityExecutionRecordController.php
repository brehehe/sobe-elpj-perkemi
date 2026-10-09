<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateActivityExecutionRecordAction;
use App\Actions\ResolveActivityExecutionRecordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveActivityExecutionRecord;
use App\Http\Requests\StoreActivityExecutionRecord;
use App\Models\Activity;
use App\Models\ActivityExecutionRecord;
use Illuminate\Http\JsonResponse;

class ActivityExecutionRecordController extends Controller
{
    public function store(StoreActivityExecutionRecord $request, Activity $activity, CreateActivityExecutionRecordAction $create): JsonResponse
    {
        $record = $create->handle($activity, $request->user(), $request->validated());

        return response()->json(['data' => $record], 201);
    }

    public function update(ResolveActivityExecutionRecord $request, Activity $activity, ActivityExecutionRecord $executionRecord, ResolveActivityExecutionRecordAction $resolve): JsonResponse
    {
        $record = $resolve->handle($activity, $executionRecord, $request->user(), $request->string('resolution_notes')->toString());

        return response()->json(['data' => $record]);
    }
}
