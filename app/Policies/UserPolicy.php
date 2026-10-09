<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.manage-users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->isGlobalAdministrator()
            || ($user->can('admin.manage-users') && $user->unit_id === $model->unit_id);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.manage-users')
            && ($user->isGlobalAdministrator() || $user->unit_id !== null);
    }

    public function update(User $user, User $model): bool
    {
        return $user->isGlobalAdministrator()
            || ($user->can('admin.manage-users')
                && ! $model->isGlobalAdministrator()
                && $user->unit_id === $model->unit_id);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
