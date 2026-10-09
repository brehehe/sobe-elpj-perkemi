<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivity;
use App\Http\Requests\UpdateActivity;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ActivityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);
        $user = request()->user();
        $activities = Activity::query()
            ->when(! $user->isGlobalAdministrator(), fn ($query) => $query->where('unit_id', $user->unit_id))
            ->with(['unit:id,code,name', 'mandate:id,number,title', 'pic:id,name,email', 'lpj:id,activity_id,status,current_version,total_amount'])
            ->withCount(['agendaItems', 'participants', 'transactions', 'evidence', 'evaluations'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ActivityResource::collection($activities);
    }

    public function store(StoreActivity $request, CreateActivityAction $create): ActivityResource
    {
        return new ActivityResource($create->handle($request->user(), $request->validated()));
    }

    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return new ActivityResource($activity
            ->load(['unit', 'mandate', 'pic', 'lpj', 'agendaItems', 'executionRecords'])
            ->loadCount(['agendaItems', 'participants', 'transactions', 'evidence', 'evaluations']));
    }

    public function update(UpdateActivity $request, Activity $activity): ActivityResource
    {
        $activity->update($request->validated());

        return new ActivityResource($activity->fresh(['unit', 'mandate', 'pic', 'lpj']));
    }

    public function destroy(Activity $activity): Response
    {
        $this->authorize('delete', $activity);
        $activity->delete();

        return response()->noContent();
    }
}
