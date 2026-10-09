<?php

namespace App\Http\Controllers;

use App\Actions\UpdateLpjDraftAction;
use App\Http\Requests\UpdateLpjDraft;
use App\Models\Lpj;
use Illuminate\Http\RedirectResponse;

class LpjDraftController extends Controller
{
    public function update(UpdateLpjDraft $request, Lpj $lpj, UpdateLpjDraftAction $update): RedirectResponse
    {
        $update->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Draft narasi LPJ berhasil disimpan.');
    }
}
