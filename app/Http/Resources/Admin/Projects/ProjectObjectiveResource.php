<?php

namespace App\Http\Resources\Admin\Projects;

use App\Models\ProjectObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectObjective */
class ProjectObjectiveResource extends JsonResource
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
            'all_team_members' => (bool) $this->all_team_members,
            'is_completed' => (bool) $this->is_completed,
            'assignees' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'uuid' => $assignment->admin->uuid,
                'name' => $assignment->admin->displayName(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
