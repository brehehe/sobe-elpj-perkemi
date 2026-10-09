<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RequestAiReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestAiReview;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;

class AiReviewController extends Controller
{
    public function store(RequestAiReview $request, RequestAiReviewAction $review): JsonResponse
    {
        $activity = Activity::findOrFail($request->string('activity_id')->toString());

        return response()->json(['data' => $review->handle($activity, $request->user(), $request->string('type')->toString())], 202);
    }
}
