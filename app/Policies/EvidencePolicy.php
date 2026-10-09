<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;

class EvidencePolicy
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
        return $user->can('evidence.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Evidence $evidence): bool
    {
        return $user->can('evidence.view') && $user->unit_id === $evidence->activity->unit_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('evidence.manage') && $user->unit_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Evidence $evidence): bool
    {
        return $user->can('evidence.manage') && $user->unit_id === $evidence->activity->unit_id;
    }

    public function review(User $user, Evidence $evidence): bool
    {
        return $user->can('evidence.review')
            && $user->unit_id === $evidence->activity->unit_id
            && $user->id !== $evidence->uploaded_by;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Evidence $evidence): bool
    {
        return $this->update($user, $evidence);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Evidence $evidence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Evidence $evidence): bool
    {
        return false;
    }
}
