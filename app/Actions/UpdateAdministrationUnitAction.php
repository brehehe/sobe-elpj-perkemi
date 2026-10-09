<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAdministrationUnitAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array{parent_id: string|null, code: string, name: string, type: string, is_active: bool} $data */
    public function handle(Unit $unit, User $actor, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $actor, $data): Unit {
            $before = $unit->only(['parent_id', 'code', 'name', 'type', 'is_active']);
            $fromStatus = $unit->is_active ? 'ACTIVE' : 'INACTIVE';
            $unit->update($data);

            $this->recordAuditEvent->handle(
                $unit,
                'unit.updated',
                $actor,
                fromStatus: $fromStatus,
                toStatus: $unit->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: [
                    'before' => $before,
                    'after' => $unit->only(['parent_id', 'code', 'name', 'type', 'is_active']),
                ],
                authority: 'admin.manage-units',
            );

            return $unit;
        });
    }
}
