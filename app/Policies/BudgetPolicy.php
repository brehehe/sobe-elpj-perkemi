<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('budgets.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->can('budgets.view') && $user->unit_id === $budget->unit_id;
    }

    public function create(User $user): bool
    {
        return $user->can('budgets.manage') && $user->unit_id !== null;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->can('budgets.manage') && $user->unit_id === $budget->unit_id;
    }

    public function review(User $user, Budget $budget): bool
    {
        return $user->can('budgets.review')
            && $user->unit_id === $budget->unit_id
            && $user->id !== $budget->owner_id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->update($user, $budget) && $budget->activities()->doesntExist();
    }

    public function restore(User $user, Budget $budget): bool
    {
        return false;
    }

    public function forceDelete(User $user, Budget $budget): bool
    {
        return false;
    }
}
