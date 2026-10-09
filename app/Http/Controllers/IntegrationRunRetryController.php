<?php

namespace App\Http\Controllers;

use App\Actions\QueueIntegrationRetryAction;
use App\Http\Requests\RetryIntegrationRunRequest;
use App\Models\IntegrationRun;
use Illuminate\Http\RedirectResponse;

class IntegrationRunRetryController extends Controller
{
    public function __invoke(
        RetryIntegrationRunRequest $request,
        IntegrationRun $integrationRun,
        QueueIntegrationRetryAction $queueRetry,
    ): RedirectResponse {
        abort_if(
            ! $request->user()->isGlobalAdministrator() && $integrationRun->unit_id !== $request->user()->unit_id,
            404,
        );

        $queueRetry->handle($integrationRun, $request->user());

        return back()->with('success', 'Retry sinkronisasi telah masuk antrean terkontrol.');
    }
}
