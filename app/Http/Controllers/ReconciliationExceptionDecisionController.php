<?php

namespace App\Http\Controllers;

use App\Actions\DecideReconciliationExceptionAction;
use App\Http\Requests\DecideReconciliationException;
use App\Models\ReconciliationException;
use Illuminate\Http\RedirectResponse;

class ReconciliationExceptionDecisionController extends Controller
{
    public function store(DecideReconciliationException $request, ReconciliationException $reconciliationException, DecideReconciliationExceptionAction $decide): RedirectResponse
    {
        $decide->handle($reconciliationException, $request->user(), $request->validated());

        return back()->with('success', 'Keputusan pengecualian rekonsiliasi berhasil dicatat.');
    }
}
