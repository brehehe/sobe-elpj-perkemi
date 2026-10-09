<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
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
            'code' => $this->code,
            'severity' => $this->severity,
            'status' => $this->status->value,
            'title' => $this->title,
            'detail' => $this->detail,
            'due_at' => $this->due_at?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'corrective_actions' => $this->whenLoaded('correctiveActions'),
        ];
    }
}
