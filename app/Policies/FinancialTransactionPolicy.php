<?php

namespace App\Policies;

use App\Models\FinancialTransaction;
use App\Models\User;

class FinancialTransactionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $user->can('activities.view') && $user->unit_id === $transaction->activity->unit_id;
    }

    public function reconcile(User $user, FinancialTransaction $transaction): bool
    {
        return $user->can('finance.reconcile') && $user->unit_id === $transaction->activity->unit_id;
    }
}
