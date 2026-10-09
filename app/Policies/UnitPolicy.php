<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Services\UnitHierarchyService;

class UnitPolicy
{
    public function __construct(private UnitHierarchyService $hierarchy) {}

    public function viewAny(User $user): bool
    {
        return $user->can('admin.manage-units');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Unit $unit): bool
    {
        return $this->hierarchy->canManage($user, $unit);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.manage-units')
            && ($user->isGlobalAdministrator() || $user->unit_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $this->hierarchy->canManage($user, $unit);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit)
            && $unit->is_active
            && $unit->users()->where('is_active', true)->doesntExist()
            && $unit->children()->where('is_active', true)->doesntExist();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return false;
    }
}
