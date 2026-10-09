<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LpjResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_version' => $this->current_version,
            'total_amount' => $this->total_amount,
            'narratives' => [
                'executive_summary' => $this->executive_summary,
                'implementation_narrative' => $this->implementation_narrative,
                'outcome_narrative' => $this->outcome_narrative,
                'financial_narrative' => $this->financial_narrative,
                'constraints' => $this->constraints,
                'follow_up_plan' => $this->follow_up_plan,
            ],
            'draft_revision' => $this->draft_revision,
            'draft_updated_at' => $this->draft_updated_at?->toIso8601String(),
            'draft_updater' => $this->whenLoaded('draftUpdater', fn () => $this->draftUpdater?->only(['id', 'name'])),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'pb_verified_at' => $this->pb_verified_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'activity' => $this->whenLoaded('activity', fn () => $this->activity->only(['id', 'code', 'title', 'unit_id'])),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner->only(['id', 'name', 'email'])),
            'current_version_record' => new LpjVersionResource($this->whenLoaded('currentVersionRecord')),
        ];
    }
}
