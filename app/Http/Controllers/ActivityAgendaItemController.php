<?php

namespace App\Http\Controllers;

use App\Actions\CreateActivityAgendaItemAction;
use App\Actions\UpdateActivityAgendaItemAction;
use App\Http\Requests\StoreActivityAgendaItem;
use App\Http\Requests\UpdateActivityAgendaItem;
use App\Models\Activity;
use App\Models\ActivityAgendaItem;
use Illuminate\Http\RedirectResponse;

class ActivityAgendaItemController extends Controller
{
    public function store(StoreActivityAgendaItem $request, Activity $activity, CreateActivityAgendaItemAction $create): RedirectResponse
    {
        $create->handle($activity, $request->user(), $request->validated());

        return back()->with('success', 'Agenda kegiatan berhasil ditambahkan.');
    }

    public function update(UpdateActivityAgendaItem $request, Activity $activity, ActivityAgendaItem $agendaItem, UpdateActivityAgendaItemAction $update): RedirectResponse
    {
        $update->handle($activity, $agendaItem, $request->user(), $request->validated());

        return back()->with('success', 'Status agenda berhasil diperbarui.');
    }
}
