<?php

namespace App\Actions;

use App\Models\ActivityTaxonomy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveActivityTaxonomyAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{code: string, name: string, description: string|null, comparable_factors: list<string>, is_active: bool}  $data
     */
    public function handle(?ActivityTaxonomy $taxonomy, User $actor, array $data): ActivityTaxonomy
    {
        return DB::transaction(function () use ($taxonomy, $actor, $data): ActivityTaxonomy {
            $isCreating = $taxonomy === null;
            $taxonomy ??= new ActivityTaxonomy(['is_system' => false]);
            $before = $taxonomy->exists ? $taxonomy->only(['code', 'name', 'description', 'comparable_factors', 'is_active']) : null;
            $fromStatus = $taxonomy->exists ? ($taxonomy->is_active ? 'ACTIVE' : 'INACTIVE') : null;
            $taxonomy->fill($data)->save();

            $this->recordAuditEvent->handle(
                $taxonomy,
                $isCreating ? 'activity_taxonomy.created' : 'activity_taxonomy.updated',
                $actor,
                fromStatus: $fromStatus,
                toStatus: $taxonomy->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: [
                    'before' => $before,
                    'after' => $taxonomy->only(['code', 'name', 'description', 'comparable_factors', 'is_active']),
                ],
                authority: 'admin.manage-master-data',
            );

            return $taxonomy;
        });
    }
}
