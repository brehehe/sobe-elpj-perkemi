<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status->value,
            'budget_ceiling' => $this->budget_ceiling,
            'unit' => $this->whenLoaded('unit', fn () => $this->unit->only(['id', 'code', 'name'])),
            'mandate' => $this->whenLoaded('mandate', fn () => $this->mandate->only(['id', 'number', 'title'])),
            'pic' => $this->whenLoaded('pic', fn () => $this->pic->only(['id', 'name', 'email'])),
            'lpj' => $this->whenLoaded('lpj', fn () => $this->lpj?->only(['id', 'status', 'current_version', 'total_amount'])),
            'agenda_items' => $this->whenLoaded('agendaItems', fn () => $this->agendaItems->map->only([
                'id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'status', 'notes', 'completed_at',
            ])),
            'execution_records' => $this->whenLoaded('executionRecords', fn () => $this->executionRecords->map->only([
                'id', 'type', 'title', 'details', 'severity', 'progress_percent', 'status', 'occurred_at', 'resolution_notes', 'resolved_at',
            ])),
            'counts' => [
                'agenda_items' => $this->whenCounted('agendaItems'),
                'participants' => $this->whenCounted('participants'),
                'transactions' => $this->whenCounted('transactions'),
                'evidence' => $this->whenCounted('evidence'),
                'evaluations' => $this->whenCounted('evaluations'),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
