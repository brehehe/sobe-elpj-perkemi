<?php

namespace App\Http\Controllers;

use App\Actions\SaveKpiCatalogAction;
use App\Http\Requests\StoreKpiCatalogRequest;
use App\Http\Requests\UpdateKpiCatalogRequest;
use App\Models\ActivityTaxonomy;
use App\Models\KpiCatalog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationKpiCatalogController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', KpiCatalog::class);
        $catalogs = KpiCatalog::query()
            ->with('activityTaxonomy:id,code,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('administration/kpis', [
            'summary' => [
                'total' => $catalogs->count(),
                'active' => $catalogs->where('is_active', true)->count(),
                'global' => $catalogs->whereNull('activity_taxonomy_id')->count(),
                'scoped' => $catalogs->whereNotNull('activity_taxonomy_id')->count(),
            ],
            'catalogs' => $catalogs->map(fn (KpiCatalog $catalog): array => [
                ...$catalog->only(['id', 'activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'formula', 'default_weight', 'is_active']),
                'formula' => $catalog->formula->value,
                'activity_taxonomy' => $catalog->activityTaxonomy?->only(['id', 'code', 'name']),
                'can_update' => request()->user()->can('update', $catalog),
                'can_delete' => request()->user()->can('delete', $catalog),
            ]),
            'taxonomies' => ActivityTaxonomy::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', KpiCatalog::class);

        return Inertia::render('administration/kpis/create', ['taxonomies' => $this->taxonomies()]);
    }

    public function store(StoreKpiCatalogRequest $request, SaveKpiCatalogAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle(null, $actor, $request->validated());

        return to_route('administration.kpis.index')->with('success', 'Definisi KPI ditambahkan dan tersedia sebagai template Planning.');
    }

    public function show(Request $request, KpiCatalog $catalog): Response
    {
        $this->authorize('view', $catalog);
        $catalog->load('activityTaxonomy:id,code,name');

        return Inertia::render('administration/kpis/show', [
            'catalog' => [...$catalog->toArray(), 'formula' => $catalog->formula->value],
            'canUpdate' => $request->user()->can('update', $catalog),
            'canDelete' => $request->user()->can('delete', $catalog),
        ]);
    }

    public function edit(KpiCatalog $catalog): Response
    {
        $this->authorize('update', $catalog);
        $catalog->load('activityTaxonomy:id,code,name');

        return Inertia::render('administration/kpis/edit', [
            'catalog' => [...$catalog->toArray(), 'formula' => $catalog->formula->value],
            'taxonomies' => $this->taxonomies(),
        ]);
    }

    public function update(UpdateKpiCatalogRequest $request, KpiCatalog $catalog, SaveKpiCatalogAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($catalog, $actor, $request->validated());

        return to_route('administration.kpis.show', $catalog)->with('success', 'Definisi KPI diperbarui.');
    }

    public function destroy(Request $request, KpiCatalog $catalog, SaveKpiCatalogAction $save): RedirectResponse
    {
        $this->authorize('delete', $catalog);
        $save->handle($catalog, $request->user(), [
            ...$catalog->only(['activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'default_weight']),
            'formula' => $catalog->formula->value,
            'is_active' => false,
        ]);

        return to_route('administration.kpis.index')->with('success', 'Definisi KPI dinonaktifkan; snapshot Planning historis tetap utuh.');
    }

    /** @return Collection<int, ActivityTaxonomy> */
    private function taxonomies(): Collection
    {
        return ActivityTaxonomy::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
    }
}
