<?php

namespace App\Http\Controllers;

use App\Actions\BuildActionCenterViewAction;
use App\Http\Requests\ActionCenterIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

class ActionCenterController extends Controller
{
    public function index(ActionCenterIndexRequest $request, BuildActionCenterViewAction $buildView): Response
    {
        return Inertia::render('action-center/index', $buildView->handle($request->user(), $request->validated()));
    }
}
