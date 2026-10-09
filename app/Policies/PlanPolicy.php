<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('planning.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->can('planning.view') && $user->unit_id === $plan->unit_id;
    }

    public function create(User $user): bool
    {
        return $user->can('planning.manage') && $user->unit_id !== null;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can('planning.manage') && $user->unit_id === $plan->unit_id;
    }

    public function review(User $user, Plan $plan): bool
    {
        return $user->can('planning.review')
            && $user->unit_id === $plan->unit_id
            && $user->id !== $plan->owner_id;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan)
            && $plan->budget()->doesntExist()
            && $plan->activities()->doesntExist();
    }

    public function restore(User $user, Plan $plan): bool
    {
        return false;
    }

    public function forceDelete(User $user, Plan $plan): bool
    {
        return false;
    }
}
