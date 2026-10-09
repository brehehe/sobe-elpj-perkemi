<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReviewEvidenceAction;
use App\Actions\StoreEvidenceAction;
use App\Enums\EvidenceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewEvidence;
use App\Http\Requests\StoreEvidence;
use App\Models\Activity;
use App\Models\Evidence;
use Illuminate\Http\JsonResponse;

class EvidenceController extends Controller
{
    public function store(StoreEvidence $request, Activity $activity, StoreEvidenceAction $store): JsonResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);
        $evidence = $store->handle($activity, $request->user(), $file, $request->validated());

        return response()->json(['data' => $evidence], 201);
    }

    public function update(ReviewEvidence $request, Evidence $evidence, ReviewEvidenceAction $review): JsonResponse
    {
        $reviewedEvidence = $review->handle(
            $evidence,
            $request->user(),
            EvidenceReviewStatus::from($request->string('status')->toString()),
            $request->string('notes')->toString() ?: null,
        );

        return response()->json(['data' => $reviewedEvidence]);
    }
}
