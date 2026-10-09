<?php

namespace App\Http\Controllers;

use App\Actions\CreateActivityExecutionRecordAction;
use App\Actions\ResolveActivityExecutionRecordAction;
use App\Http\Requests\ResolveActivityExecutionRecord;
use App\Http\Requests\StoreActivityExecutionRecord;
use App\Models\Activity;
use App\Models\ActivityExecutionRecord;
use Illuminate\Http\RedirectResponse;

class ActivityExecutionRecordController extends Controller
{
    public function store(StoreActivityExecutionRecord $request, Activity $activity, CreateActivityExecutionRecordAction $create): RedirectResponse
    {
        $create->handle($activity, $request->user(), $request->validated());

        return back()->with('success', 'Catatan pelaksanaan berhasil disimpan.');
    }

    public function update(ResolveActivityExecutionRecord $request, Activity $activity, ActivityExecutionRecord $executionRecord, ResolveActivityExecutionRecordAction $resolve): RedirectResponse
    {
        $resolve->handle($activity, $executionRecord, $request->user(), $request->string('resolution_notes')->toString());

        return back()->with('success', 'Issue pelaksanaan berhasil diselesaikan.');
    }
}
