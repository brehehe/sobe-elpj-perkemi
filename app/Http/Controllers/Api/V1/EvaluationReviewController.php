<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReviewEvaluationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewEvaluation;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;

class EvaluationReviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReviewEvaluation $request, Evaluation $evaluation, ReviewEvaluationAction $review): JsonResponse
    {
        return response()->json([
            'data' => $review->handle($evaluation, $request->user(), $request->validated()),
        ]);
    }
}
