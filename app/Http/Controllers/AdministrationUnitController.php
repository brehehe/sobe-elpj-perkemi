<?php

namespace App\Http\Controllers;

use App\Actions\CreateAdministrationUnitAction;
use App\Actions\UpdateAdministrationUnitAction;
use App\Http\Requests\StoreAdministrationUnitRequest;
use App\Http\Requests\UpdateAdministrationUnitRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationUnitController extends Controller
{
    public function index(Request $request, UnitHierarchyService $hierarchy): Response
    {
        $this->authorize('viewAny', Unit::class);
        /** @var User $actor */
        $actor = $request->user();
        $actor->loadMissing('unit');
        $manageableIds = $hierarchy->manageableIds($actor);
        $units = Unit::query()
            ->whereIn('id', $manageableIds)
            ->withCount(['users', 'users as active_users_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'code', 'name', 'type', 'is_active']);

        return Inertia::render('administration/units', [
            'summary' => [
                'total' => $units->count(),
                'active' => $units->where('is_active', true)->count(),
                'people' => $units->sum('users_count'),
                'levels' => $units->pluck('type')->unique()->count(),
            ],
            'scope' => [
                'is_global' => $actor->isGlobalAdministrator(),
                'root_unit_id' => $actor->unit_id,
                'label' => $actor->isGlobalAdministrator() ? 'Seluruh organisasi' : ($actor->unit?->name ?? 'Unit tidak tersedia'),
            ],
            'units' => $this->tree($units),
            'options' => $units->map(fn (Unit $unit): array => [
                ...$unit->only(['id', 'parent_id', 'code', 'name', 'type', 'is_active']),
                'can_update' => $actor->can('update', $unit),
                'can_delete' => $actor->can('delete', $unit)
                    && $unit->is_active
                    && $unit->active_users_count === 0
                    && $unit->children()->where('is_active', true)->doesntExist(),
            ])->values(),
        ]);
    }

    public function store(StoreAdministrationUnitRequest $request, CreateAdministrationUnitAction $createUnit): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $createUnit->handle($actor, $request->validated());

        return to_route('administration.units.index')->with('success', 'Unit organisasi dibuat dan dicatat pada audit trail.');
    }

    public function create(Request $request, UnitHierarchyService $hierarchy): Response
    {
        $this->authorize('create', Unit::class);

        return Inertia::render('administration/units/create', $this->formOptions($request->user(), $hierarchy));
    }

    public function show(Request $request, Unit $unit): Response
    {
        $this->authorize('view', $unit);
        $unit->load(['parent:id,code,name', 'children:id,parent_id,code,name,type,is_active'])
            ->loadCount(['users', 'users as active_users_count' => fn ($query) => $query->where('is_active', true)]);

        return Inertia::render('administration/units/show', [
            'unit' => $unit,
            'canUpdate' => $request->user()->can('update', $unit),
            'canDelete' => $request->user()->can('delete', $unit)
                && $unit->is_active
                && $unit->active_users_count === 0
                && $unit->children->where('is_active', true)->isEmpty(),
        ]);
    }

    public function edit(Request $request, Unit $unit, UnitHierarchyService $hierarchy): Response
    {
        $this->authorize('update', $unit);

        return Inertia::render('administration/units/edit', [
            'unit' => $unit,
            ...$this->formOptions($request->user(), $hierarchy),
        ]);
    }

    public function update(UpdateAdministrationUnitRequest $request, Unit $unit, UpdateAdministrationUnitAction $updateUnit): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $updateUnit->handle($unit, $actor, $request->validated());

        return to_route('administration.units.show', $unit)->with('success', 'Unit organisasi diperbarui dan dicatat pada audit trail.');
    }

    public function destroy(Request $request, Unit $unit, UpdateAdministrationUnitAction $updateUnit): RedirectResponse
    {
        $this->authorize('delete', $unit);
        if ($unit->users()->where('is_active', true)->exists() || $unit->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['unit' => 'Unit tidak dapat dinonaktifkan selama masih memiliki pengguna atau unit turunan aktif.']);
        }

        $updateUnit->handle($unit, $request->user(), [
            ...$unit->only(['parent_id', 'code', 'name', 'type']),
            'is_active' => false,
        ]);

        return to_route('administration.units.index')->with('success', 'Unit dinonaktifkan tanpa menghapus riwayat organisasi.');
    }

    /** @param Collection<int, Unit> $units */
    private function tree(Collection $units, ?string $parentId = null): array
    {
        return $units
            ->filter(fn (Unit $unit) => $unit->parent_id === $parentId || ($parentId === null && ! $units->contains('id', $unit->parent_id)))
            ->map(fn (Unit $unit): array => [
                ...$unit->only(['id', 'parent_id', 'code', 'name', 'type', 'is_active']),
                'users_count' => $unit->users_count,
                'active_users_count' => $unit->active_users_count,
                'children' => $this->tree($units, $unit->id),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function formOptions(User $actor, UnitHierarchyService $hierarchy): array
    {
        $actor->loadMissing('unit');

        return [
            'scope' => [
                'is_global' => $actor->isGlobalAdministrator(),
                'root_unit_id' => $actor->unit_id,
                'label' => $actor->isGlobalAdministrator() ? 'Seluruh organisasi' : ($actor->unit?->name ?? 'Unit tidak tersedia'),
            ],
            'options' => Unit::query()
                ->whereIn('id', $hierarchy->manageableIds($actor))
                ->orderBy('name')
                ->get(['id', 'parent_id', 'code', 'name', 'type', 'is_active']),
        ];
    }
}
