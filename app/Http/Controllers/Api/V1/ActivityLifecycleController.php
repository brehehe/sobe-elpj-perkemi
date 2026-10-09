<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteActivityAction;
use App\Actions\StartActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

class ActivityLifecycleController extends Controller
{
    public function start(Activity $activity, StartActivityAction $start): ActivityResource
    {
        $this->authorize('transition', $activity);

        return new ActivityResource($start->handle($activity, request()->user()));
    }

    public function complete(Activity $activity, CompleteActivityAction $complete): ActivityResource
    {
        $this->authorize('transition', $activity);

        return new ActivityResource($complete->handle($activity, request()->user()));
    }
}
