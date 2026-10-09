<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationHierarchyController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(request()->user()->can('hierarchy.view'), 403);

        $units = Unit::query()->withCount('users')->orderBy('name')->get();

        return Inertia::render('organization/hierarchy', ['units' => $this->tree($units)]);
    }

    /** @param Collection<int, Unit> $units */
    private function tree(Collection $units, ?string $parentId = null): array
    {
        return $units
            ->filter(fn (Unit $unit) => $unit->parent_id === $parentId)
            ->map(fn (Unit $unit): array => [
                ...$unit->only(['id', 'parent_id', 'code', 'name', 'type', 'is_active']),
                'users_count' => $unit->users_count,
                'children' => $this->tree($units, $unit->id),
            ])
            ->values()
            ->all();
    }
}
