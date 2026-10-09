<?php

namespace App\Policies;

use App\Models\ActivityTaxonomy;
use App\Models\User;

class ActivityTaxonomyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.manage-master-data');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ActivityTaxonomy $activityTaxonomy): bool
    {
        return $user->can('admin.manage-master-data');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.manage-master-data');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ActivityTaxonomy $activityTaxonomy): bool
    {
        return $user->can('admin.manage-master-data');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ActivityTaxonomy $activityTaxonomy): bool
    {
        return $this->update($user, $activityTaxonomy) && $activityTaxonomy->is_active;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ActivityTaxonomy $activityTaxonomy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ActivityTaxonomy $activityTaxonomy): bool
    {
        return false;
    }
}
