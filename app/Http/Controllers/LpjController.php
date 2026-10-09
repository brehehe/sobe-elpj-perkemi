<?php

namespace App\Http\Controllers;

use App\Actions\ApproveLpjAction;
use App\Actions\ArchiveLpjAction;
use App\Actions\BuildLpjIndexViewAction;
use App\Actions\CancelAiReviewAction;
use App\Actions\OpenFindingAction;
use App\Actions\RequestAiReviewAction;
use App\Actions\ReviewAiOutputAction;
use App\Actions\ReviewCorrectiveActionAction;
use App\Actions\SubmitCorrectiveActionAction;
use App\Actions\SubmitLpjAction;
use App\Actions\VerifyLpjAction;
use App\Enums\LpjStatus;
use App\Http\Requests\ApproveLpj;
use App\Http\Requests\ArchiveLpj;
use App\Http\Requests\CancelAiReview;
use App\Http\Requests\LpjIndexRequest;
use App\Http\Requests\RequestAiReview;
use App\Http\Requests\ReviewAiOutput;
use App\Http\Requests\ReviewCorrectiveAction;
use App\Http\Requests\StoreCorrectiveAction;
use App\Http\Requests\StoreFinding;
use App\Http\Requests\SubmitLpj;
use App\Http\Requests\VerifyLpj;
use App\Models\Activity;
use App\Models\AiReview;
use App\Models\CorrectiveAction;
use App\Models\Disposition;
use App\Models\Finding;
use App\Models\Lpj;
use App\Models\User;
use App\Services\LpjApprovalGate;
use App\Services\LpjSubmissionGate;
use App\Services\LpjVerificationGate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LpjController extends Controller
{
    public function index(LpjIndexRequest $request, BuildLpjIndexViewAction $buildView): Response
    {
        return Inertia::render('lpjs/index', $buildView->handle($request->user(), $request->validated()));
    }

    public function show(Lpj $lpj, LpjSubmissionGate $submissionGate, LpjVerificationGate $verificationGate, LpjApprovalGate $approvalGate): Response
    {
        $this->authorize('view', $lpj);
        $lpj->load([
            'activity.unit:id,code,name,is_active', 'activity.mandate', 'activity.plan:id,code,status,current_version',
            'activity.budget:id,code,status,current_version', 'activity.aiReviews.requester:id,name', 'activity.aiReviews.reviewer:id,name', 'activity.participants',
            'activity.agendaItems', 'activity.executionRecords', 'activity.transactions.reconciliation.exceptions', 'activity.evidence', 'activity.evaluations', 'owner:id,name,email',
            'draftUpdater:id,name',
            'versions' => fn ($query) => $query->orderByDesc('version'),
            'currentVersionRecord.verifications.verifier:id,name',
            'currentVersionRecord.findings.owner:id,name',
            'currentVersionRecord.findings.correctiveActions.submitter:id,name',
            'currentVersionRecord.approvals.decisionMaker:id,name',
            'currentVersionRecord.dispositions.sender:id,name',
            'currentVersionRecord.dispositions.recipient:id,name',
            'currentVersionRecord.dispositions.reviewer:id,name',
            'currentVersionRecord.verifiedPack',
            'currentVersionRecord.archive',
        ]);
        $lpj->currentVersionRecord?->dispositions->each(function (Disposition $disposition): void {
            $disposition->setAttribute('can_respond', request()->user()->can('respond', $disposition));
            $disposition->setAttribute('can_review', request()->user()->can('review', $disposition));
        });

        return Inertia::render('lpjs/show', [
            'lpj' => $lpj,
            'composer' => $submissionGate->evaluate($lpj),
            'verificationWorkflow' => $verificationGate->evaluate($lpj, request()->user()),
            'approvalWorkflow' => $approvalGate->evaluate($lpj, request()->user()),
            'can' => [
                'submit' => request()->user()->can('submit', $lpj),
                'exportDraft' => request()->user()->can('exportDraft', $lpj)
                    && in_array($lpj->status, [LpjStatus::Draft, LpjStatus::ReadyToSubmit, LpjStatus::Returned], true),
                'verify' => request()->user()->can('verify', $lpj),
                'approve' => request()->user()->can('approve', $lpj),
                'pbVerify' => request()->user()->can('pbVerify', $lpj),
                'archive' => request()->user()->can('archive', $lpj),
                'openFinding' => request()->user()->can('findings.manage'),
                'requestAi' => request()->user()->can('ai.request'),
                'reviewAi' => request()->user()->can('ai.review'),
                'createDisposition' => request()->user()->can('create', [Disposition::class, $lpj]),
                'respondDisposition' => request()->user()->can('dispositions.respond'),
            ],
            'dispositionUsers' => User::query()
                ->where('unit_id', $lpj->activity->unit_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function submit(SubmitLpj $request, Lpj $lpj, SubmitLpjAction $submit): RedirectResponse
    {
        $submit->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Snapshot LPJ berhasil diajukan dan masuk antrean verifikasi.');
    }

    public function verify(VerifyLpj $request, Lpj $lpj, VerifyLpjAction $verify): RedirectResponse
    {
        $verify->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Hasil verifikasi berhasil dicatat.');
    }

    public function storeFinding(StoreFinding $request, Lpj $lpj, OpenFindingAction $open): RedirectResponse
    {
        $open->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Temuan dibuat dan tugas tindak lanjut dikirim.');
    }

    public function storeCorrectiveAction(StoreCorrectiveAction $request, Finding $finding, SubmitCorrectiveActionAction $submit): RedirectResponse
    {
        $submit->handle($finding, $request->user(), $request->string('action')->toString(), $request->file('evidence'));

        return back()->with('success', 'Tindakan korektif berhasil diajukan.');
    }

    public function reviewCorrectiveAction(ReviewCorrectiveAction $request, Finding $finding, CorrectiveAction $correctiveAction, ReviewCorrectiveActionAction $review): RedirectResponse
    {
        $review->handle(
            $finding,
            $correctiveAction,
            $request->user(),
            $request->string('decision')->toString(),
            $request->string('notes')->toString() ?: null,
        );

        return back()->with('success', 'Pemeriksaan tindakan korektif berhasil dicatat.');
    }

    public function approve(ApproveLpj $request, Lpj $lpj, ApproveLpjAction $approve): RedirectResponse
    {
        $approve->handle($lpj, $request->user(), $request->validated());

        return back()->with('success', 'Keputusan persetujuan berhasil dicatat.');
    }

    public function archive(ArchiveLpj $request, Lpj $lpj, ArchiveLpjAction $archive): RedirectResponse
    {
        $archive->handle($lpj, $request->user());

        return back()->with('success', 'Paket LPJ berhasil diarsipkan secara immutable.');
    }

    public function requestAi(RequestAiReview $request, RequestAiReviewAction $review): RedirectResponse
    {
        $activity = Activity::findOrFail($request->string('activity_id')->toString());
        $review->handle($activity, $request->user(), $request->string('type')->toString());

        return back()->with('success', 'Pemeriksaan pendukung masuk antrean. Hasil akan diperbarui otomatis dan tetap memerlukan tinjauan manusia.');
    }

    public function reviewAi(ReviewAiOutput $request, AiReview $aiReview, ReviewAiOutputAction $review): RedirectResponse
    {
        $review->handle($aiReview, $request->user(), $request->validated());

        return back()->with('success', 'Review manusia atas rekomendasi AI berhasil dicatat.');
    }

    public function cancelAi(CancelAiReview $request, AiReview $aiReview, CancelAiReviewAction $cancel): RedirectResponse
    {
        $cancel->handle($aiReview, $request->user());

        return back()->with('success', 'Pemeriksaan AI dibatalkan. Workflow utama tetap dapat dilanjutkan.');
    }
}
