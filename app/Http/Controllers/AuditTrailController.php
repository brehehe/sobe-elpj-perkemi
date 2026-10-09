<?php

namespace App\Http\Controllers;

use App\Actions\BuildAuditTrailViewAction;
use App\Actions\RecordAuditEventAction;
use App\Http\Requests\AuditTrailIndexRequest;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditTrailController extends Controller
{
    public function index(AuditTrailIndexRequest $request, BuildAuditTrailViewAction $auditTrail): Response
    {
        return Inertia::render('audit/index', $auditTrail->handle($request->user(), $request->validated()));
    }

    public function export(
        AuditTrailIndexRequest $request,
        BuildAuditTrailViewAction $auditTrail,
        RecordAuditEventAction $recordAuditEvent,
    ): StreamedResponse {
        $user = $request->user();
        $filters = $request->validated();
        $exportEvent = $recordAuditEvent->handle($user, 'audit.exported', $user, payload: [
            'filters' => array_filter($filters, fn (mixed $value): bool => filled($value)),
        ], authority: 'audit.view');

        return response()->streamDownload(function () use ($auditTrail, $exportEvent, $filters, $user): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, [
                'Waktu', 'Event ID', 'Correlation ID', 'Request ID', 'Event', 'Actor', 'Authority', 'Unit', 'Object',
                'Object ID', 'Object Version', 'Status Sebelum', 'Status Sesudah', 'Hasil', 'Channel', 'Alasan',
                'Evidence References', 'Payload Version', 'Metadata',
            ]);

            foreach ($auditTrail->exportRows($user, $filters, $exportEvent->id) as $event) {
                fputcsv($stream, array_map($this->csvCell(...), [
                    $event['created_at'],
                    $event['event_id'],
                    $event['correlation_id'],
                    $event['request_id'],
                    $event['event_type'],
                    $event['actor']['name'],
                    $event['authority'],
                    $event['unit']['name'],
                    $event['object']['label'],
                    $event['object']['id'],
                    $event['object']['version'],
                    $event['change']['before'],
                    $event['change']['after'],
                    $event['result'],
                    $event['source_channel'],
                    $event['reason'],
                    implode(' | ', $event['evidence_references']),
                    $event['payload_version'],
                    json_encode($event['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]));
            }

            fclose($stream);
        }, 'audit-trail-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function csvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/\A[=+\-@]/u', $cell) === 1 ? "'".$cell : $cell;
    }
}
