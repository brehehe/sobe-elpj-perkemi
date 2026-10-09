<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

class UnitHierarchyService
{
    /** @return list<string> */
    public function manageableIds(User $actor): array
    {
        if ($actor->isGlobalAdministrator()) {
            return Unit::query()->pluck('id')->all();
        }

        if ($actor->unit_id === null) {
            return [];
        }

        return $this->descendantIds(Unit::query()->get(['id', 'parent_id']), $actor->unit_id);
    }

    public function canManage(User $actor, Unit $unit): bool
    {
        return $actor->can('admin.manage-units')
            && in_array($unit->id, $this->manageableIds($actor), true);
    }

    public function parentIsManageable(User $actor, ?string $parentId): bool
    {
        if ($parentId === null) {
            return $actor->isGlobalAdministrator();
        }

        return in_array($parentId, $this->manageableIds($actor), true);
    }

    public function createsCycle(Unit $unit, ?string $parentId): bool
    {
        return $parentId !== null
            && in_array($parentId, $this->descendantIds(Unit::query()->get(['id', 'parent_id']), $unit->id), true);
    }

    public function parentTypeIsValid(string $type, ?Unit $parent): bool
    {
        return match ($type) {
            'PUSAT' => $parent === null,
            'PROVINSI' => $parent?->type === 'PUSAT',
            'KOTA', 'KABUPATEN' => $parent?->type === 'PROVINSI',
            'DOJO' => in_array($parent?->type, ['KOTA', 'KABUPATEN'], true),
            'UNIT' => true,
            default => false,
        };
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return list<string>
     */
    public function descendantIds(Collection $units, string $unitId): array
    {
        $children = $units->groupBy(fn (Unit $unit): string => $unit->parent_id ?? 'root');
        $ids = [];
        $queue = [$unitId];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $ids[] = $currentId;

            foreach ($children->get($currentId, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
