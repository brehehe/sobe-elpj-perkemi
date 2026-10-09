<?php

namespace App\Services;

use App\Models\SmartTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SmartTaskVisibilityService
{
    public function __construct(private SmartTaskPresentationService $presenter) {}

    public function queryFor(User $user): Builder
    {
        $query = SmartTask::query();
        if ($user->isGlobalAdministrator()) {
            return $query;
        }

        $localTypes = $this->presenter->unassignedTypesVisibleTo($user);
        $globalTypes = $this->presenter->unassignedTypesVisibleTo($user, globalOnly: true);
        $permissions = $user->getAllPermissions()->pluck('name')->all();
        $roles = $user->getRoleNames()->all();

        return $query->where(function (Builder $visibility) use ($user, $localTypes, $globalTypes, $permissions, $roles): void {
            $visibility->where('assignee_id', $user->id)
                ->orWhere(function (Builder $local) use ($user, $localTypes, $permissions, $roles): void {
                    $local->whereNull('assignee_id')
                        ->where('unit_id', $user->unit_id)
                        ->where(function (Builder $recipient) use ($localTypes, $permissions, $roles): void {
                            $recipient->whereIn('type', $localTypes)
                                ->orWhereIn('recipient_permission', $permissions)
                                ->when($roles !== [], fn (Builder $role) => $role->orWhereIn('metadata->to_role', $roles));
                        });
                })
                ->when($globalTypes !== [], fn (Builder $assigned) => $assigned->orWhere(function (Builder $global) use ($globalTypes): void {
                    $global->whereNull('assignee_id')->whereIn('type', $globalTypes);
                }));
        });
    }
}
