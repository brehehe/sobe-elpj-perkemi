<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReviewCorrectiveActionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCorrectiveAction;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use Illuminate\Http\JsonResponse;

class CorrectiveActionReviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReviewCorrectiveAction $request, Finding $finding, CorrectiveAction $correctiveAction, ReviewCorrectiveActionAction $review): JsonResponse
    {
        $reviewedFinding = $review->handle(
            $finding,
            $correctiveAction,
            $request->user(),
            $request->string('decision')->toString(),
            $request->string('notes')->toString() ?: null,
        );

        return response()->json(['data' => $reviewedFinding->load('correctiveActions')]);
    }
}
