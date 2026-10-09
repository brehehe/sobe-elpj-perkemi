<?php

namespace App\Actions;

use App\Models\KpiCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveKpiCatalogAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{activity_taxonomy_id: string|null, code: string, name: string, definition: string, unit: string, formula: string, default_weight: numeric-string|int|float, is_active: bool}  $data
     */
    public function handle(?KpiCatalog $catalog, User $actor, array $data): KpiCatalog
    {
        return DB::transaction(function () use ($catalog, $actor, $data): KpiCatalog {
            $isCreating = $catalog === null;
            $catalog ??= new KpiCatalog;
            $before = $catalog->exists ? $catalog->only(['activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'formula', 'default_weight', 'is_active']) : null;
            $fromStatus = $catalog->exists ? ($catalog->is_active ? 'ACTIVE' : 'INACTIVE') : null;
            $catalog->fill($data)->save();

            $this->recordAuditEvent->handle(
                $catalog,
                $isCreating ? 'kpi_catalog.created' : 'kpi_catalog.updated',
                $actor,
                fromStatus: $fromStatus,
                toStatus: $catalog->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: [
                    'before' => $before,
                    'after' => $catalog->only(['activity_taxonomy_id', 'code', 'name', 'definition', 'unit', 'formula', 'default_weight', 'is_active']),
                ],
                authority: 'admin.manage-master-data',
            );

            return $catalog;
        });
    }
}
