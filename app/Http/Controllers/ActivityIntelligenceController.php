<?php

namespace App\Http\Controllers;

use App\Actions\BuildActivityIntelligenceViewAction;
use App\Http\Requests\ActivityIntelligenceIndexRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ActivityIntelligenceController extends Controller
{
    public function __invoke(ActivityIntelligenceIndexRequest $request, BuildActivityIntelligenceViewAction $intelligence): Response
    {
        /** @var User $user */
        $user = $request->user();
        $mode = $request->routeIs('next-activity.index') ? 'NEXT_ACTIVITY' : 'ANALYSIS';

        return Inertia::render('activity-intelligence/index', $intelligence->handle($user, $request->validated(), $mode));
    }
}
