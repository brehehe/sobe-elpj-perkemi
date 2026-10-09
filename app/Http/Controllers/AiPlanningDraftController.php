<?php

namespace App\Http\Controllers;

use App\Actions\CreatePlanningDraftFromAiReviewAction;
use App\Http\Requests\CreatePlanningDraftFromAiReview;
use App\Models\AiReview;
use Illuminate\Http\RedirectResponse;

class AiPlanningDraftController extends Controller
{
    public function __invoke(
        CreatePlanningDraftFromAiReview $request,
        AiReview $aiReview,
        CreatePlanningDraftFromAiReviewAction $createDraft,
    ): RedirectResponse {
        $plan = $createDraft->handle($aiReview, $request->user());

        return to_route('planning.index', ['focus' => $plan->id])
            ->with('success', 'Rekomendasi yang telah direview dibuat sebagai draft planning. Lengkapi lalu ajukan untuk review formal.');
    }
}
