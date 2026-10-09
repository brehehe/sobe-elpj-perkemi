<?php

namespace App\Http\Controllers;

use App\Actions\ReviewEvidenceAction;
use App\Enums\EvidenceReviewStatus;
use App\Http\Requests\ReviewEvidence;
use App\Models\Evidence;
use Illuminate\Http\RedirectResponse;

class EvidenceReviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReviewEvidence $request, Evidence $evidence, ReviewEvidenceAction $review): RedirectResponse
    {
        $review->handle(
            $evidence,
            $request->user(),
            EvidenceReviewStatus::from($request->string('status')->toString()),
            $request->string('notes')->toString() ?: null,
        );

        return back()->with('success', 'Hasil pemeriksaan bukti berhasil dicatat.');
    }
}
