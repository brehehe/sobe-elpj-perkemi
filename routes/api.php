<?php

use App\Http\Controllers\Api\V1\ActivityAgendaItemController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ActivityExecutionRecordController;
use App\Http\Controllers\Api\V1\ActivityLifecycleController;
use App\Http\Controllers\Api\V1\AiReviewController;
use App\Http\Controllers\Api\V1\CorrectiveActionController;
use App\Http\Controllers\Api\V1\CorrectiveActionReviewController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\EvaluationReviewController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\FinanceSyncController;
use App\Http\Controllers\Api\V1\FindingController;
use App\Http\Controllers\Api\V1\LpjApprovalController;
use App\Http\Controllers\Api\V1\LpjArchiveController;
use App\Http\Controllers\Api\V1\LpjController;
use App\Http\Controllers\Api\V1\LpjDraftController;
use App\Http\Controllers\Api\V1\LpjSubmissionController;
use App\Http\Controllers\Api\V1\LpjVerificationController;
use App\Http\Controllers\Api\V1\PublicVerificationController;
use App\Http\Controllers\Api\V1\ReconciliationExceptionController;
use App\Http\Controllers\Api\V1\ReconciliationExceptionDecisionController;
use Illuminate\Support\Facades\Route;

Route::get('v1/verifications/{token}', PublicVerificationController::class)
    ->middleware('throttle:30,1')
    ->name('api.v1.verifications.show');

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::apiResource('activities', ActivityController::class);
    Route::post('activities/{activity}/starts', [ActivityLifecycleController::class, 'start'])->name('activities.starts.store');
    Route::post('activities/{activity}/completions', [ActivityLifecycleController::class, 'complete'])->name('activities.completions.store');
    Route::post('activities/{activity}/agenda-items', [ActivityAgendaItemController::class, 'store'])->name('activities.agenda-items.store');
    Route::patch('activities/{activity}/agenda-items/{agendaItem}', [ActivityAgendaItemController::class, 'update'])->scopeBindings()->name('activities.agenda-items.update');
    Route::post('activities/{activity}/execution-records', [ActivityExecutionRecordController::class, 'store'])->name('activities.execution-records.store');
    Route::patch('activities/{activity}/execution-records/{executionRecord}', [ActivityExecutionRecordController::class, 'update'])->scopeBindings()->name('activities.execution-records.update');
    Route::apiResource('lpjs', LpjController::class)->only(['index', 'show']);
    Route::patch('lpjs/{lpj}/draft', [LpjDraftController::class, 'update'])->name('lpjs.draft.update');
    Route::post('activities/{activity}/evidence', [EvidenceController::class, 'store'])->middleware('throttle:uploads')->name('activities.evidence.store');
    Route::post('activities/{activity}/evaluations', [EvaluationController::class, 'store'])->name('activities.evaluations.store');
    Route::patch('evaluations/{evaluation}/review', EvaluationReviewController::class)->name('evaluations.review');
    Route::patch('evidence/{evidence}', [EvidenceController::class, 'update'])->name('evidence.update');
    Route::patch('reconciliation-exceptions/{reconciliationException}', [ReconciliationExceptionController::class, 'update'])->name('reconciliation-exceptions.update');
    Route::post('reconciliation-exceptions/{reconciliationException}/decisions', [ReconciliationExceptionDecisionController::class, 'store'])->name('reconciliation-exceptions.decisions.store');
    Route::post('lpjs/{lpj}/submissions', [LpjSubmissionController::class, 'store'])->name('lpjs.submissions.store');
    Route::post('lpjs/{lpj}/verifications', [LpjVerificationController::class, 'store'])->name('lpjs.verifications.store');
    Route::post('lpjs/{lpj}/findings', [FindingController::class, 'store'])->name('lpjs.findings.store');
    Route::post('findings/{finding}/corrective-actions', [CorrectiveActionController::class, 'store'])->name('findings.corrective-actions.store');
    Route::post('findings/{finding}/corrective-actions/{correctiveAction}/review', CorrectiveActionReviewController::class)->scopeBindings()->name('findings.corrective-actions.review');
    Route::post('lpjs/{lpj}/approvals', [LpjApprovalController::class, 'store'])->name('lpjs.approvals.store');
    Route::post('lpjs/{lpj}/archives', [LpjArchiveController::class, 'store'])->name('lpjs.archives.store');
    Route::post('integrations/finance/transactions', [FinanceSyncController::class, 'store'])->middleware('throttle:60,1')->name('integrations.finance.transactions.store');
    Route::post('ai-reviews', [AiReviewController::class, 'store'])->middleware('throttle:ai')->name('ai-reviews.store');
});
