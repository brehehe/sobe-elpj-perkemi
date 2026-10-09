<?php

namespace App\Http\Controllers;

use App\Actions\CompleteActivityAction;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;

class ActivityCompletionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Activity $activity, CompleteActivityAction $complete): RedirectResponse
    {
        $this->authorize('transition', $activity);
        $complete->handle($activity, request()->user());

        return back()->with('success', 'Kegiatan selesai. Tugas evaluasi dan kesiapan LPJ telah diperbarui.');
    }
}
