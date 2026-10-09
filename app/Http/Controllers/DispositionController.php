<?php

namespace App\Http\Controllers;

use App\Actions\CreateDispositionAction;
use App\Actions\UpdateDispositionStatusAction;
use App\Http\Requests\StoreDisposition;
use App\Http\Requests\UpdateDisposition;
use App\Models\Disposition;
use App\Models\Lpj;
use Illuminate\Http\RedirectResponse;

class DispositionController extends Controller
{
    public function store(StoreDisposition $request, Lpj $lpj, CreateDispositionAction $create): RedirectResponse
    {
        $create->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Disposisi berhasil dibuat dan dikirim.');
    }

    public function update(UpdateDisposition $request, Disposition $disposition, UpdateDispositionStatusAction $update): RedirectResponse
    {
        $update->handle($disposition, $request->user(), $request->validated());

        return back()->with('success', 'Status disposisi berhasil diperbarui.');
    }
}
