<?php

namespace App\Http\Controllers;

use App\Actions\ResolveReconciliationExceptionAction;
use App\Enums\ReconciliationExceptionStatus;
use App\Http\Requests\ResolveReconciliationException;
use App\Models\ReconciliationException;
use Illuminate\Http\RedirectResponse;

class ReconciliationExceptionController extends Controller
{
    public function update(ResolveReconciliationException $request, ReconciliationException $reconciliationException, ResolveReconciliationExceptionAction $resolve): RedirectResponse
    {
        $exception = $resolve->handle($reconciliationException, $request->user(), $request->validated());

        return back()->with('success', $exception->status === ReconciliationExceptionStatus::PendingApproval
            ? 'Permintaan pengecualian resmi berhasil dikirim untuk diputuskan.'
            : 'Exception rekonsiliasi berhasil diselesaikan.');
    }
}
