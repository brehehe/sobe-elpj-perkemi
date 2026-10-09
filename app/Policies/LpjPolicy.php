<?php

namespace App\Policies;

use App\Enums\ApprovalLevel;
use App\Models\Lpj;
use App\Models\User;
use App\Services\LpjApprovalGate;

class LpjPolicy
{
    public function __construct(private LpjApprovalGate $approvalGate) {}

    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('lpjs.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lpj $lpj): bool
    {
        return $user->can('lpjs.view')
            && ($user->unit_id === $lpj->activity->unit_id || $user->can('lpjs.pb_verify'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('lpjs.submit') && $user->unit_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lpj $lpj): bool
    {
        return $user->can('lpjs.submit') && $user->unit_id === $lpj->activity->unit_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lpj $lpj): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lpj $lpj): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lpj $lpj): bool
    {
        return false;
    }

    public function submit(User $user, Lpj $lpj): bool
    {
        return $this->update($user, $lpj);
    }

    public function exportDraft(User $user, Lpj $lpj): bool
    {
        return $this->view($user, $lpj);
    }

    public function verify(User $user, Lpj $lpj): bool
    {
        return $user->can('lpjs.verify') && $user->unit_id === $lpj->activity->unit_id;
    }

    public function approve(User $user, Lpj $lpj): bool
    {
        return $this->approvalGate->hasAuthority($lpj, $user, ApprovalLevel::Approver);
    }

    public function pbVerify(User $user, Lpj $lpj): bool
    {
        return $this->approvalGate->hasAuthority($lpj, $user, ApprovalLevel::Pb);
    }

    public function archive(User $user, Lpj $lpj): bool
    {
        return $user->can('lpjs.archive') && $user->unit_id === $lpj->activity->unit_id;
    }
}
