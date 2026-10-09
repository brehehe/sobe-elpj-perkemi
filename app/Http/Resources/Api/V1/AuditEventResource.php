<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditEventResource extends JsonResource
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
            'event_type' => $this->event_type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'payload' => $this->payload,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
