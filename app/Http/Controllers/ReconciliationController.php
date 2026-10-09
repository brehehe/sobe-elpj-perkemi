<?php

namespace App\Http\Controllers;

use App\Actions\ReconcileTransactionAction;
use App\Http\Requests\StoreReconciliation;
use App\Models\FinancialTransaction;
use Illuminate\Http\RedirectResponse;

class ReconciliationController extends Controller
{
    public function store(StoreReconciliation $request, FinancialTransaction $transaction, ReconcileTransactionAction $reconcile): RedirectResponse
    {
        $reconcile->handle($transaction, $request->user(), $request->validated());

        return back()->with('success', 'Hasil rekonsiliasi transaksi berhasil dicatat.');
    }
}
