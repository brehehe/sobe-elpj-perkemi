<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->can('activities.view')
            && ($user->isGlobalAdministrator() || $user->unit_id === $evaluation->activity->unit_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('activities.manage') && $user->unit_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Evaluation $evaluation): bool
    {
        return $user->can('activities.manage')
            && $user->id === $evaluation->activity->pic_id
            && ($user->isGlobalAdministrator() || $user->unit_id === $evaluation->activity->unit_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    public function review(User $user, Evaluation $evaluation): bool
    {
        return $user->can('lpjs.verify.substantive')
            && $user->id !== $evaluation->created_by
            && ($user->isGlobalAdministrator() || $user->unit_id === $evaluation->activity->unit_id);
    }
}
