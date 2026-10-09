<?php

namespace App\Http\Controllers;

use App\Actions\RequestNextActivityRecommendationAction;
use App\Enums\AiReviewStatus;
use App\Http\Requests\RequestNextActivityRecommendation;
use Illuminate\Http\RedirectResponse;

class NextActivityRecommendationController extends Controller
{
    public function __invoke(RequestNextActivityRecommendation $request, RequestNextActivityRecommendationAction $recommendation): RedirectResponse
    {
        $review = $recommendation->handle($request->user(), $request->validated());
        $message = match ($review->status) {
            AiReviewStatus::Queued, AiReviewStatus::Running => 'Paket rekomendasi cohort masuk antrean AI dan akan diperbarui otomatis sebelum review manusia.',
            AiReviewStatus::ReviewRequired => 'Paket dengan sumber yang sama sudah siap untuk review manusia dan dibuka kembali.',
            default => 'Paket dengan sumber yang sama sudah tercatat; hasil review sebelumnya dibuka kembali.',
        };

        return to_route('intelligence.index', ['type' => 'NEXT_ACTIVITY'])
            ->with('success', $message);
    }
}
