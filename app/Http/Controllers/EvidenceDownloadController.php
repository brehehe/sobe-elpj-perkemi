<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Enums\EvidenceScanStatus;
use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceDownloadController extends Controller
{
    public function __invoke(Evidence $evidence, RecordAuditEventAction $audit): StreamedResponse
    {
        $this->authorize('view', $evidence);
        abort_unless(
            $evidence->scan_status === EvidenceScanStatus::Registered,
            423,
            'Berkas belum lolos pemeriksaan keamanan.',
        );
        $audit->handle($evidence, 'evidence.accessed', request()->user(), payload: ['evidence_id' => $evidence->id]);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
