<?php

namespace App\Http\Resources\Admin\Research;

use App\Enums\ResearchStatusEnum;
use App\Models\Research;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Research */
class ResearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'scope' => $this->scope,
            'methodology' => $this->methodology,
            'status' => $this->status,
            'status_label' => ResearchStatusEnum::from($this->status)->label(),
            'starts_at' => $this->starts_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'objectives' => $this->whenLoaded('objectives', fn () => ResearchObjectiveResource::collection($this->objectives)),
            'milestones' => $this->whenLoaded('milestones', fn () => ResearchMilestoneResource::collection($this->milestones)),
            'deliverables' => $this->whenLoaded('deliverables', fn () => ResearchDeliverableResource::collection($this->deliverables)),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
