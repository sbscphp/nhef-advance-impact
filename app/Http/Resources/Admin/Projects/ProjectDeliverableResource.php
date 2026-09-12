<?php

namespace App\Http\Resources\Admin\Projects;

use App\Models\ProjectDeliverable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectDeliverable */
class ProjectDeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'starts_at' => $this->starts_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'all_team_members' => (bool) $this->all_team_members,
            'is_completed' => (bool) $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'status' => $this->status(),
            'days_overdue' => $this->daysOverdue(),
            'milestone' => $this->whenLoaded('milestone', fn () => $this->milestone === null ? null : [
                'uuid' => $this->milestone->uuid,
                'title' => $this->milestone->title,
            ]),
            'assignees' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'uuid' => $assignment->admin->uuid,
                'name' => $assignment->admin->displayName(),
            ])),
            'notify_all_team_members' => (bool) $this->notify_all_team_members,
            'notify_recipients' => $this->whenLoaded('notifyRecipients', fn () => $this->notifyRecipients->map(fn ($admin) => [
                'uuid' => $admin->uuid,
                'name' => $admin->displayName(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
