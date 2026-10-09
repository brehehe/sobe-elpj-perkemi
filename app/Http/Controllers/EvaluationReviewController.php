<?php

namespace App\Http\Controllers;

use App\Actions\ReviewEvaluationAction;
use App\Http\Requests\ReviewEvaluation;
use App\Models\Evaluation;
use Illuminate\Http\RedirectResponse;

class EvaluationReviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReviewEvaluation $request, Evaluation $evaluation, ReviewEvaluationAction $review): RedirectResponse
    {
        $review->handle($evaluation, $request->user(), $request->validated());

        return back()->with('success', 'Keputusan review evaluasi berhasil dicatat.');
    }
}
