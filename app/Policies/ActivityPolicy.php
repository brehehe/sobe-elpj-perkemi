<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\LpjStatus;
use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activities.view') && $user->unit_id === $activity->unit_id;
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
    public function update(User $user, Activity $activity): bool
    {
        return $user->can('activities.manage') && $user->unit_id === $activity->unit_id;
    }

    public function transition(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity) && $activity->pic_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity)
            && $activity->status === ActivityStatus::Planned
            && $activity->participants()->doesntExist()
            && $activity->agendaItems()->doesntExist()
            && $activity->executionRecords()->doesntExist()
            && $activity->transactions()->doesntExist()
            && $activity->evidence()->doesntExist()
            && $activity->evaluations()->doesntExist()
            && $activity->lpj()->where('status', LpjStatus::Draft)->where('current_version', 0)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }
}
