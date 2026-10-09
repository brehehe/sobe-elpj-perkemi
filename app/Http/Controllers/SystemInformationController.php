<?php

namespace App\Http\Controllers;

use App\Actions\BuildSystemInformationViewAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemInformationController extends Controller
{
    public function __invoke(Request $request, BuildSystemInformationViewAction $buildView): Response
    {
        return Inertia::render('system/about', $buildView->handle($request->user()));
    }
}
