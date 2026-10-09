<?php

namespace App\Policies;

use App\Models\Mandate;
use App\Models\User;

class MandatePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('mandates.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Mandate $mandate): bool
    {
        return $user->can('mandates.view') && $user->unit_id === $mandate->unit_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('mandates.manage') && $user->unit_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mandate $mandate): bool
    {
        return $user->can('mandates.manage') && $user->unit_id === $mandate->unit_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mandate $mandate): bool
    {
        return $this->update($user, $mandate)
            && $mandate->activities()->doesntExist()
            && $mandate->plans()->doesntExist();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Mandate $mandate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Mandate $mandate): bool
    {
        return false;
    }
}
