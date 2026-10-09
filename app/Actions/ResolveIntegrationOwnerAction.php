<?php

namespace App\Actions;

use App\Models\User;

class ResolveIntegrationOwnerAction
{
    public function handle(?string $unitId, ?User $fallback = null): ?User
    {
        if ($unitId === null) {
            return $fallback?->is_active ? $fallback : null;
        }

        $owner = User::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', config('integrations.owner_role', 'Finance PIC')))
            ->orderBy('name')
            ->first();

        if ($owner !== null) {
            return $owner;
        }

        if ($fallback?->unit_id === $unitId && $fallback->is_active && $fallback->can('integrations.manage')) {
            return $fallback;
        }

        return User::permission('integrations.manage')
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
    }
}
