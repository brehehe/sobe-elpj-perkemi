<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAdministrationUnitAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array{parent_id: string|null, code: string, name: string, type: string, is_active: bool} $data */
    public function handle(User $actor, array $data): Unit
    {
        return DB::transaction(function () use ($actor, $data): Unit {
            $unit = Unit::query()->create($data);

            $this->recordAuditEvent->handle(
                $unit,
                'unit.created',
                $actor,
                toStatus: $unit->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: $unit->only(['parent_id', 'code', 'name', 'type', 'is_active']),
                authority: 'admin.manage-units',
            );

            return $unit;
        });
    }
}
