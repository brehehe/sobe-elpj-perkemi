<?php

namespace App\Http\Controllers;

use App\Actions\ExportLpjDraftAction;
use App\Models\Lpj;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LpjDraftExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Lpj $lpj, ExportLpjDraftAction $export): StreamedResponse
    {
        $this->authorize('exportDraft', $lpj);
        ['filename' => $filename, 'document' => $document] = $export->handle($lpj, request()->user());

        return response()->streamDownload(
            fn () => print view('exports.lpj-draft', ['document' => $document])->render(),
            $filename,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }
}
