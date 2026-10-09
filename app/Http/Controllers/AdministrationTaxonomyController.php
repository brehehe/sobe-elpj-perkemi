<?php

namespace App\Http\Controllers;

use App\Actions\SaveActivityTaxonomyAction;
use App\Http\Requests\StoreActivityTaxonomyRequest;
use App\Http\Requests\UpdateActivityTaxonomyRequest;
use App\Models\ActivityTaxonomy;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationTaxonomyController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ActivityTaxonomy::class);
        $usageCounts = PlanVersion::query()
            ->selectRaw('activity_type, count(*) as aggregate')
            ->groupBy('activity_type')
            ->pluck('aggregate', 'activity_type');
        $taxonomies = ActivityTaxonomy::query()->orderByDesc('is_active')->orderBy('name')->get();

        return Inertia::render('administration/taxonomies', [
            'summary' => [
                'total' => $taxonomies->count(),
                'active' => $taxonomies->where('is_active', true)->count(),
                'system' => $taxonomies->where('is_system', true)->count(),
                'used' => $taxonomies->filter(fn (ActivityTaxonomy $taxonomy) => (int) ($usageCounts[$taxonomy->code] ?? 0) > 0)->count(),
            ],
            'taxonomies' => $taxonomies->map(fn (ActivityTaxonomy $taxonomy): array => [
                ...$taxonomy->only(['id', 'code', 'name', 'description', 'comparable_factors', 'is_active', 'is_system']),
                'usage_count' => (int) ($usageCounts[$taxonomy->code] ?? 0),
                'can_update' => request()->user()->can('update', $taxonomy),
                'can_delete' => request()->user()->can('delete', $taxonomy) && (int) ($usageCounts[$taxonomy->code] ?? 0) === 0,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ActivityTaxonomy::class);

        return Inertia::render('administration/taxonomies/create');
    }

    public function store(StoreActivityTaxonomyRequest $request, SaveActivityTaxonomyAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle(null, $actor, $request->validated());

        return to_route('administration.taxonomies.index')->with('success', 'Taksonomi kegiatan ditambahkan dan langsung tersedia di Planning.');
    }

    public function show(Request $request, ActivityTaxonomy $taxonomy): Response
    {
        $this->authorize('view', $taxonomy);
        $usageCount = PlanVersion::query()->where('activity_type', $taxonomy->code)->count();
        $taxonomy->load('kpiCatalogs:id,activity_taxonomy_id,code,name,is_active');

        return Inertia::render('administration/taxonomies/show', [
            'taxonomy' => [...$taxonomy->toArray(), 'usage_count' => $usageCount],
            'canUpdate' => $request->user()->can('update', $taxonomy),
            'canDelete' => $request->user()->can('delete', $taxonomy) && $usageCount === 0,
        ]);
    }

    public function edit(ActivityTaxonomy $taxonomy): Response
    {
        $this->authorize('update', $taxonomy);

        return Inertia::render('administration/taxonomies/edit', ['taxonomy' => $taxonomy]);
    }

    public function update(UpdateActivityTaxonomyRequest $request, ActivityTaxonomy $taxonomy, SaveActivityTaxonomyAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($taxonomy, $actor, $request->validated());

        return to_route('administration.taxonomies.show', $taxonomy)->with('success', 'Taksonomi kegiatan diperbarui.');
    }

    public function destroy(Request $request, ActivityTaxonomy $taxonomy, SaveActivityTaxonomyAction $save): RedirectResponse
    {
        $this->authorize('delete', $taxonomy);
        if (PlanVersion::query()->where('activity_type', $taxonomy->code)->exists()) {
            throw ValidationException::withMessages(['taxonomy' => 'Taksonomi yang sudah dipakai Planning tidak dapat dinonaktifkan.']);
        }

        $save->handle($taxonomy, $request->user(), [
            ...$taxonomy->only(['code', 'name', 'description', 'comparable_factors']),
            'is_active' => false,
        ]);

        return to_route('administration.taxonomies.index')->with('success', 'Taksonomi dinonaktifkan tanpa mengubah snapshot Planning historis.');
    }
}
