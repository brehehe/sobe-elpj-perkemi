<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvaluationAction;
use App\Actions\RecordAuditEventAction;
use App\Actions\StoreEvidenceAction;
use App\Http\Requests\StoreEvaluation;
use App\Http\Requests\StoreEvidence;
use App\Http\Requests\StoreParticipant;
use App\Models\Activity;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;

class ActivitySourceController extends Controller
{
    public function storeParticipant(StoreParticipant $request, Activity $activity, RecordAuditEventAction $audit): RedirectResponse
    {
        $participant = Participant::create([
            ...$request->validated(),
            'activity_id' => $activity->id,
            'source' => 'MANUAL',
        ]);
        $audit->handle($activity, 'participant.created', $request->user(), payload: ['participant_id' => $participant->id]);

        return back()->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function storeEvaluation(StoreEvaluation $request, Activity $activity, CreateEvaluationAction $create): RedirectResponse
    {
        $create->handle($activity, $request->user(), $request->validated());

        return back()->with('success', 'Evaluasi kegiatan berhasil dicatat.');
    }

    public function storeEvidence(StoreEvidence $request, Activity $activity, StoreEvidenceAction $store): RedirectResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);
        $store->handle($activity, $request->user(), $file, $request->validated());

        return back()->with('success', 'Bukti privat berhasil diunggah dan masuk antrean pemeriksaan.');
    }
}
