<?php

namespace App\Policies;

use App\Models\Disposition;
use App\Models\Lpj;
use App\Models\User;

class DispositionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isGlobalAdministrator() ? true : null;
    }

    public function create(User $user, Lpj $lpj): bool
    {
        return $user->can('dispositions.manage')
            && $user->unit_id === $lpj->activity->unit_id;
    }

    public function view(User $user, Disposition $disposition): bool
    {
        return $user->can('lpjs.view') && $user->unit_id === $disposition->unit_id;
    }

    public function update(User $user, Disposition $disposition): bool
    {
        return $this->respond($user, $disposition) || $this->review($user, $disposition);
    }

    public function respond(User $user, Disposition $disposition): bool
    {
        return $user->unit_id === $disposition->unit_id
            && $user->can('dispositions.respond')
            && ($disposition->to_user_id === null || $disposition->to_user_id === $user->id);
    }

    public function review(User $user, Disposition $disposition): bool
    {
        return $user->unit_id === $disposition->unit_id
            && $user->can('dispositions.manage')
            && $disposition->to_user_id !== $user->id;
    }
}
