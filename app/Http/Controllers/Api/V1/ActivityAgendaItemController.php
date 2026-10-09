<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateActivityAgendaItemAction;
use App\Actions\UpdateActivityAgendaItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityAgendaItem;
use App\Http\Requests\UpdateActivityAgendaItem;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use Illuminate\Http\JsonResponse;

class ActivityAgendaItemController extends Controller
{
    public function store(StoreActivityAgendaItem $request, Activity $activity, CreateActivityAgendaItemAction $create): JsonResponse
    {
        $agendaItem = $create->handle($activity, $request->user(), $request->validated());

        return response()->json(['data' => $agendaItem], 201);
    }

    public function update(UpdateActivityAgendaItem $request, Activity $activity, ActivityAgendaItem $agendaItem, UpdateActivityAgendaItemAction $update): JsonResponse
    {
        return response()->json(['data' => $update->handle($activity, $agendaItem, $request->user(), $request->validated())]);
    }
}
