<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationExceptionResource extends JsonResource
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
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status->value,
            'detail' => $this->detail,
            'resolution_type' => $this->resolution_type,
            'resolution' => $this->resolution,
            'is_approved_exception' => $this->is_approved_exception,
            'decision' => $this->decision?->value,
            'decision_notes' => $this->decision_notes,
            'authority_policy_version' => $this->authority_policy_version,
            'due_at' => $this->due_at?->toIso8601String(),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester?->only(['id', 'name'])),
            'decision_maker' => $this->whenLoaded('decisionMaker', fn () => $this->decisionMaker?->only(['id', 'name'])),
        ];
    }
}
