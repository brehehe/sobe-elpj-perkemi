<?php

namespace App\Http\Controllers;

use App\Enums\AiReviewStatus;
use App\Enums\FindingStatus;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\Finding;
use App\Models\Lpj;
use App\Services\SmartTaskVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SmartTaskVisibilityService $taskVisibility): Response
    {
        $user = request()->user();
        $activities = Activity::query()->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id));
        $lpjs = Lpj::query()->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)));
        $findings = Finding::query()->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->whereHas('lpjVersion.lpj.activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)));
        $canViewIntelligence = $user->can('ai.request') || $user->can('ai.review');
        $intelligence = AiReview::query()
            ->whereIn('status', [AiReviewStatus::ReviewRequired->value, AiReviewStatus::Succeeded->value])
            ->whereNotNull('output')
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)));
        $formatInsight = fn (AiReview $review): array => [
            'id' => $review->id,
            'type' => $review->type,
            'model' => $review->model,
            'engine' => str_starts_with((string) $review->model, 'local-rules') ? 'RULE_BASED' : 'OPENAI',
            'human_review_status' => $review->human_review_status,
            'summary' => data_get($review->output, 'summary'),
            'recommendation' => data_get($review->output, 'recommendations.0'),
            'confidence' => $review->confidence === null ? null : (int) round((float) $review->confidence * 100),
            'activity' => $review->activity->only(['id', 'code', 'title']),
        ];

        return Inertia::render('dashboard', [
            'metrics' => [
                'activities' => (clone $activities)->count(),
                'lpjs_in_process' => (clone $lpjs)->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count(),
                'open_findings' => (clone $findings)->whereIn('status', [FindingStatus::Open->value, FindingStatus::CapaSubmitted->value])->count(),
                'accounted_amount' => (float) (clone $lpjs)->sum('total_amount'),
            ],
            'pipeline' => (clone $lpjs)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'tasks' => $taskVisibility->queryFor($user)
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->with('activity:id,code,title')
                ->orderByRaw("CASE priority WHEN 'CRITICAL' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 ELSE 4 END")
                ->orderBy('due_at')
                ->limit(8)
                ->get(),
            'recentLpjs' => (clone $lpjs)->with(['activity:id,code,title', 'owner:id,name'])->latest('updated_at')->limit(8)->get(),
            'upcomingActivities' => (clone $activities)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(['id', 'code', 'title', 'starts_at', 'status']),
            'canViewIntelligence' => $canViewIntelligence,
            'intelligenceBriefs' => $canViewIntelligence
                ? (clone $intelligence)->where('type', '!=', 'NEXT_ACTIVITY')->with('activity:id,code,title')->latest()->limit(3)->get()->map($formatInsight)
                : [],
            'nextActivityRecommendations' => $canViewIntelligence
                ? (clone $intelligence)->where('type', 'NEXT_ACTIVITY')->with('activity:id,code,title')->latest()->limit(3)->get()->map($formatInsight)
                : [],
        ]);
    }
}
