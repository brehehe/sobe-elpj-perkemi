<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LpjVersionResource extends JsonResource
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
            'version' => $this->version,
            'status' => $this->status->value,
            'checksum' => $this->checksum,
            'is_immutable' => $this->is_immutable,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'snapshot' => $this->snapshot,
            'verifications' => $this->whenLoaded('verifications'),
            'findings' => FindingResource::collection($this->whenLoaded('findings')),
            'approvals' => $this->whenLoaded('approvals'),
            'verified_pack' => $this->whenLoaded('verifiedPack'),
            'archive' => $this->whenLoaded('archive'),
        ];
    }
}
