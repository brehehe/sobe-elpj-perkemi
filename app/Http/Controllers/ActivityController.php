<?php

namespace App\Http\Controllers;

use App\Actions\CreateActivityAction;
use App\Actions\RecordAuditEventAction;
use App\Enums\BudgetStatus;
use App\Http\Requests\StoreActivity;
use App\Http\Requests\UpdateActivity;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Evidence;
use App\Models\User;
use App\Services\ActivityCompletionGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Activity::class);
        $user = request()->user();

        return Inertia::render('activities/index', [
            'activities' => Activity::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->with(['mandate:id,number,title', 'pic:id,name', 'lpj:id,activity_id,status,current_version'])
                ->withCount(['agendaItems', 'participants', 'transactions', 'evidence', 'executionRecords', 'evaluations'])
                ->latest('starts_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Activity $activity): array => [
                    ...$activity->toArray(),
                    'can_update' => $user->can('update', $activity),
                    'can_delete' => $user->can('delete', $activity) && $this->isDeletable($activity),
                ]),
            'canCreate' => $user->can('create', Activity::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Activity::class);
        $user = request()->user();

        return Inertia::render('activities/create', [
            'budgets' => Budget::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->where('status', BudgetStatus::Approved)
                ->with(['plan:id,mandate_id,code,current_version', 'plan.mandate:id,number,title', 'currentVersionRecord:id,budget_id,total'])
                ->orderBy('code')
                ->get(['id', 'plan_id', 'unit_id', 'code', 'current_version']),
            'users' => User::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->where('is_active', true)
                ->with('unit:id,code,name')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'name']),
        ]);
    }

    public function store(StoreActivity $request, CreateActivityAction $create): RedirectResponse
    {
        $activity = $create->handle($request->user(), $request->validated());

        return redirect()->route('activities.show', $activity)->with('success', 'Kegiatan dan draf LPJ berhasil dibuat.');
    }

    public function show(Activity $activity, ActivityCompletionGate $completionGate): Response
    {
        $this->authorize('view', $activity);
        $activity->load([
            'unit:id,code,name', 'mandate', 'plan.currentVersionRecord', 'budget.currentVersionRecord.lines', 'pic:id,name,email', 'participants',
            'transactions' => fn ($query) => $query->latest('transaction_date'),
            'evidence' => fn ($query) => $query->with(['uploader:id,name', 'reviewer:id,name'])->latest(),
            'evaluations' => fn ($query) => $query
                ->with(['creator:id,name', 'reviewer:id,name', 'kpiDefinitions.result.evidence:id,title,version'])
                ->latest('version'),
            'agendaItems' => fn ($query) => $query->with('completedBy:id,name')->orderBy('starts_at')->orderBy('id'),
            'executionRecords' => fn ($query) => $query->with(['creator:id,name', 'resolver:id,name'])->latest('occurred_at')->latest('id'),
            'lpj', 'lpj.currentVersionRecord.verifications', 'lpj.currentVersionRecord.findings',
        ]);
        $activity->evidence->each(fn (Evidence $evidence) => $evidence->setAttribute(
            'can_review',
            request()->user()->can('review', $evidence),
        )->setAttribute(
            'lifecycle_status',
            $evidence->lifecycleStatus(),
        )->setAttribute(
            'download_url',
            $evidence->isDownloadable()
                ? URL::temporarySignedRoute('evidence.download', now()->addMinutes(5), ['evidence' => $evidence])
                : null,
        ));
        $activity->evaluations->each(fn ($evaluation) => $evaluation->setAttribute(
            'can_review',
            request()->user()->can('review', $evaluation),
        ));

        return Inertia::render('activities/show', [
            'activity' => $activity,
            'canManage' => request()->user()->can('update', $activity),
            'canEvaluate' => request()->user()->isGlobalAdministrator() || (
                request()->user()->can('update', $activity) && $activity->pic_id === request()->user()->id
            ),
            'canTransition' => request()->user()->can('transition', $activity),
            'canUploadEvidence' => request()->user()->can('create', Evidence::class),
            'evaluationPlanKpis' => data_get($activity->plan_snapshot, 'kpis') ?: ($activity->plan?->currentVersionRecord?->kpis ?? []),
            'evaluationTargetParticipants' => data_get($activity->plan_snapshot, 'target_participants') ?: $activity->plan?->currentVersionRecord?->target_participants,
            'completionReadiness' => $completionGate->evaluate($activity),
            'canDelete' => request()->user()->can('delete', $activity) && $this->isDeletable($activity),
        ]);
    }

    public function edit(Activity $activity): Response
    {
        $this->authorize('update', $activity);

        return Inertia::render('activities/edit', ['activity' => $activity]);
    }

    public function update(UpdateActivity $request, Activity $activity, RecordAuditEventAction $audit): RedirectResponse
    {
        $before = $activity->only(['title', 'description', 'location', 'starts_at', 'ends_at']);
        $activity->update($request->validated());
        $audit->handle($activity, 'activity.updated', $request->user(), fromStatus: $activity->status->value, toStatus: $activity->status->value, payload: [
            'before' => $before,
            'after' => $activity->fresh()->only(array_keys($before)),
        ]);

        return to_route('activities.show', $activity)->with('success', 'Data pelaksanaan kegiatan diperbarui.');
    }

    public function destroy(Request $request, Activity $activity, RecordAuditEventAction $audit): RedirectResponse
    {
        $this->authorize('delete', $activity);
        $hasOperationalData = $activity->participants()->exists()
            || $activity->agendaItems()->exists()
            || $activity->executionRecords()->exists()
            || $activity->transactions()->exists()
            || $activity->evidence()->exists()
            || $activity->evaluations()->exists();
        $hasDraftLpjOnly = $activity->lpj()->where('status', 'DRAFT')->where('current_version', 0)->exists();

        if ($activity->status->value !== 'PLANNED' || $hasOperationalData || ! $hasDraftLpjOnly) {
            throw ValidationException::withMessages(['activity' => 'Kegiatan hanya dapat dihapus sebelum pelaksanaan dan sebelum memiliki data sumber.']);
        }

        DB::transaction(function () use ($activity, $audit, $request): void {
            $audit->handle($activity, 'activity.deleted', $request->user(), fromStatus: $activity->status->value, toStatus: 'DELETED');
            $activity->lpj()->delete();
            $activity->delete();
        });

        return to_route('activities.index')->with('success', 'Kegiatan draft dan LPJ draft terkait berhasil dihapus.');
    }

    private function isDeletable(Activity $activity): bool
    {
        $hasOperationalData = ($activity->participants_count ?? $activity->participants()->count()) > 0
            || ($activity->agenda_items_count ?? $activity->agendaItems()->count()) > 0
            || ($activity->execution_records_count ?? $activity->executionRecords()->count()) > 0
            || ($activity->transactions_count ?? $activity->transactions()->count()) > 0
            || ($activity->evidence_count ?? $activity->evidence()->count()) > 0
            || ($activity->evaluations_count ?? $activity->evaluations()->count()) > 0;

        return $activity->status->value === 'PLANNED'
            && ! $hasOperationalData
            && $activity->lpj()->where('status', 'DRAFT')->where('current_version', 0)->exists();
    }
}
