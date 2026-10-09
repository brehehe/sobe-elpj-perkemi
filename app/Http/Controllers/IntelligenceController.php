<?php

namespace App\Http\Controllers;

use App\Actions\BuildIntelligenceViewAction;
use App\Http\Requests\IntelligenceIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

class IntelligenceController extends Controller
{
    public function __invoke(IntelligenceIndexRequest $request, BuildIntelligenceViewAction $intelligence): Response
    {
        return Inertia::render('intelligence/index', $intelligence->handle($request->user(), $request->validated()));
    }
}
