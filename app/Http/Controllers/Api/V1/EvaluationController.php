<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateEvaluationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvaluation;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;

class EvaluationController extends Controller
{
    public function store(StoreEvaluation $request, Activity $activity, CreateEvaluationAction $create): JsonResponse
    {
        $evaluation = $create->handle($activity, $request->user(), $request->validated());

        return response()->json(['data' => $evaluation], 201);
    }
}
