<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Actions\ReviewBudgetAction;
use App\Actions\SubmitBudgetAction;
use App\Enums\PlanStatus;
use App\Http\Requests\ReviewBudget;
use App\Http\Requests\StoreBudget;
use App\Models\Budget;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Budget::class);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('budgets/index', [
            'budgets' => Budget::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->with(['plan:id,code,status,current_version', 'plan.currentVersionRecord:id,plan_id,activity_type,objective', 'unit:id,code,name', 'owner:id,name', 'currentVersionRecord.lines'])
                ->withCount('activities')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Budget $budget): array => [
                    ...$budget->toArray(),
                    'can_update' => $user->can('update', $budget),
                    'can_delete' => $user->can('delete', $budget) && $budget->activities_count === 0,
                    'can_review' => $user->can('review', $budget),
                ]),
            'canCreate' => $user->can('create', Budget::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Budget::class);

        return Inertia::render('budgets/create', [
            'plans' => $this->availablePlans($request->user()),
            'selectedPlanId' => $request->string('plan')->toString(),
        ]);
    }

    public function store(StoreBudget $request, SubmitBudgetAction $submit): RedirectResponse
    {
        $version = $submit->handle(null, $request->user(), $request->validated());

        return to_route('budgets.show', $version->budget)->with('success', 'RAB versi pertama berhasil diajukan.');
    }

    public function show(Request $request, Budget $budget): Response
    {
        $this->authorize('view', $budget);
        /** @var User $user */
        $user = $request->user();
        $budget->load([
            'plan:id,mandate_id,code,status,current_version',
            'plan.mandate:id,number,title',
            'plan.currentVersionRecord:id,plan_id,activity_type,objective',
            'unit:id,code,name',
            'owner:id,name',
            'versions' => fn ($query) => $query->with(['creator:id,name', 'reviewer:id,name', 'lines'])->latest('version'),
        ]);

        return Inertia::render('budgets/show', [
            'budget' => $budget,
            'canUpdate' => $user->can('update', $budget),
            'canDelete' => $user->can('delete', $budget) && $budget->activities()->doesntExist(),
            'canReview' => $user->can('review', $budget),
        ]);
    }

    public function edit(Budget $budget): Response
    {
        $this->authorize('update', $budget);
        abort_unless(in_array($budget->status->value, ['DRAFT', 'RETURNED'], true), 409, 'RAB pada status ini tidak dapat diubah.');
        $budget->load(['plan.currentVersionRecord:id,plan_id,activity_type,objective', 'currentVersionRecord.lines']);

        return Inertia::render('budgets/edit', ['budget' => $budget]);
    }

    public function update(StoreBudget $request, Budget $budget, SubmitBudgetAction $submit): RedirectResponse
    {
        $submit->handle($budget, $request->user(), $request->validated());

        return to_route('budgets.show', $budget)->with('success', 'Versi RAB baru berhasil diajukan.');
    }

    public function destroy(Request $request, Budget $budget, RecordAuditEventAction $audit): RedirectResponse
    {
        $this->authorize('delete', $budget);
        if ($budget->activities()->exists()) {
            throw ValidationException::withMessages([
                'budget' => 'RAB tidak dapat dihapus karena sudah menjadi dasar kegiatan.',
            ]);
        }

        $audit->handle($budget, 'budget.deleted', $request->user(), fromStatus: $budget->status->value, toStatus: 'DELETED');
        $budget->delete();

        return to_route('budgets.index')->with('success', 'RAB dipindahkan dari daftar aktif tanpa menghapus riwayat versinya.');
    }

    public function storeVersion(StoreBudget $request, Budget $budget, SubmitBudgetAction $submit): RedirectResponse
    {
        return $this->update($request, $budget, $submit);
    }

    public function review(ReviewBudget $request, Budget $budget, ReviewBudgetAction $review): RedirectResponse
    {
        $review->handle($budget, $request->user(), $request->validated());

        return to_route('budgets.show', $budget)->with('success', 'Keputusan review RAB berhasil dicatat.');
    }

    /** @return Collection<int, Plan> */
    private function availablePlans(User $user): Collection
    {
        return Plan::query()
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
            ->whereIn('status', [PlanStatus::Submitted->value, PlanStatus::Approved->value])
            ->whereDoesntHave('budget')
            ->with('currentVersionRecord:id,plan_id,activity_type,objective')
            ->orderBy('code')
            ->get(['id', 'code', 'status']);
    }
}
