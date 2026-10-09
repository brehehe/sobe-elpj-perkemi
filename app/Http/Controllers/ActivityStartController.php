<?php

namespace App\Http\Controllers;

use App\Actions\StartActivityAction;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;

class ActivityStartController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Activity $activity, StartActivityAction $start): RedirectResponse
    {
        $this->authorize('transition', $activity);
        $start->handle($activity, request()->user());

        return back()->with('success', 'Pelaksanaan kegiatan dimulai. Agenda dan bukti kini dapat diperbarui.');
    }
}
