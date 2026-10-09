<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Actions\ReviewPlanAction;
use App\Actions\SubmitPlanAction;
use App\Http\Requests\ReviewPlan;
use App\Http\Requests\StorePlan;
use App\Models\ActivityTaxonomy;
use App\Models\KpiCatalog;
use App\Models\Mandate;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plan::class);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('planning/index', [
            'plans' => Plan::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->with(['mandate:id,number,title', 'unit:id,code,name', 'owner:id,name', 'currentVersionRecord', 'budget:id,plan_id,status,code,current_version'])
                ->withCount('activities')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Plan $plan): array => [
                    ...$plan->toArray(),
                    'can_update' => $user->can('update', $plan),
                    'can_delete' => $user->can('delete', $plan)
                        && $plan->budget === null
                        && $plan->activities_count === 0,
                    'can_review' => $user->can('review', $plan),
                ]),
            'canCreate' => $user->can('create', Plan::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Plan::class);

        return Inertia::render('planning/create', [
            ...$this->formOptions($request->user()),
            'selectedMandateId' => $request->string('mandate')->toString(),
        ]);
    }

    public function store(StorePlan $request, SubmitPlanAction $submit): RedirectResponse
    {
        $version = $submit->handle(null, $request->user(), $request->validated());

        return to_route('planning.show', $version->plan)->with('success', 'Planning versi pertama berhasil diajukan.');
    }

    public function show(Request $request, Plan $plan): Response
    {
        $this->authorize('view', $plan);
        /** @var User $user */
        $user = $request->user();
        $plan->load([
            'mandate:id,number,title,issued_at,expires_at,status',
            'unit:id,code,name',
            'owner:id,name',
            'budget:id,plan_id,code,status,current_version',
            'sourceAiReview:id,type,human_review_status',
            'versions' => fn ($query) => $query->with(['creator:id,name', 'reviewer:id,name'])->latest('version'),
        ]);

        return Inertia::render('planning/show', [
            'plan' => $plan,
            'canUpdate' => $user->can('update', $plan),
            'canDelete' => $user->can('delete', $plan)
                && $plan->budget === null
                && $plan->activities()->doesntExist(),
            'canReview' => $user->can('review', $plan),
        ]);
    }

    public function edit(Request $request, Plan $plan): Response
    {
        $this->authorize('update', $plan);
        abort_unless(in_array($plan->status->value, ['DRAFT', 'RETURNED'], true), 409, 'Planning pada status ini tidak dapat diubah.');
        $plan->load(['mandate:id,number,title', 'currentVersionRecord']);

        return Inertia::render('planning/edit', [
            'plan' => $plan,
            ...$this->formOptions($request->user(), $plan),
        ]);
    }

    public function update(StorePlan $request, Plan $plan, SubmitPlanAction $submit): RedirectResponse
    {
        $submit->handle($plan, $request->user(), $request->validated());

        return to_route('planning.show', $plan)->with('success', 'Versi planning baru berhasil diajukan.');
    }

    public function destroy(Request $request, Plan $plan, RecordAuditEventAction $audit): RedirectResponse
    {
        $this->authorize('delete', $plan);
        if ($plan->budget()->exists() || $plan->activities()->exists()) {
            throw ValidationException::withMessages([
                'planning' => 'Planning tidak dapat dihapus karena sudah memiliki RAB atau kegiatan.',
            ]);
        }

        $audit->handle($plan, 'plan.deleted', $request->user(), fromStatus: $plan->status->value, toStatus: 'DELETED');
        $plan->delete();

        return to_route('planning.index')->with('success', 'Planning dipindahkan dari daftar aktif tanpa menghapus riwayat versinya.');
    }

    public function storeVersion(StorePlan $request, Plan $plan, SubmitPlanAction $submit): RedirectResponse
    {
        return $this->update($request, $plan, $submit);
    }

    public function review(ReviewPlan $request, Plan $plan, ReviewPlanAction $review): RedirectResponse
    {
        $review->handle($plan, $request->user(), $request->validated());

        return to_route('planning.show', $plan)->with('success', 'Keputusan review planning berhasil dicatat.');
    }

    /** @return array<string, mixed> */
    private function formOptions(User $user, ?Plan $plan = null): array
    {
        return [
            'mandates' => Mandate::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->where(function (Builder $query) use ($plan): void {
                    $query->where('status', 'ACTIVE')
                        ->when($plan !== null, fn (Builder $mandateQuery) => $mandateQuery->orWhereKey($plan->mandate_id));
                })
                ->orderByDesc('issued_at')
                ->get(['id', 'number', 'title']),
            'activityTypes' => ActivityTaxonomy::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'kpiCatalogs' => KpiCatalog::query()
                ->where('is_active', true)
                ->with('activityTaxonomy:id,code,name')
                ->orderBy('name')
                ->get(['id', 'activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'formula', 'default_weight'])
                ->map(fn (KpiCatalog $catalog): array => [
                    ...$catalog->only(['id', 'activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'default_weight']),
                    'formula' => $catalog->formula->value,
                    'activity_type' => $catalog->activityTaxonomy?->code,
                ]),
        ];
    }
}
