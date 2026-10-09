<?php

namespace App\Policies;

use App\Models\KpiCatalog;
use App\Models\User;

class KpiCatalogPolicy
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
    public function view(User $user, KpiCatalog $kpiCatalog): bool
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
    public function update(User $user, KpiCatalog $kpiCatalog): bool
    {
        return $user->can('admin.manage-master-data');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, KpiCatalog $kpiCatalog): bool
    {
        return $this->update($user, $kpiCatalog) && $kpiCatalog->is_active;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, KpiCatalog $kpiCatalog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, KpiCatalog $kpiCatalog): bool
    {
        return false;
    }
}
