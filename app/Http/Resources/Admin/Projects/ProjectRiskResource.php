<?php

namespace App\Http\Resources\Admin\Projects;

use App\Enums\ProjectRiskSeverityEnum;
use App\Enums\ProjectRiskStatusEnum;
use App\Models\ProjectRisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectRisk */
class ProjectRiskResource extends JsonResource
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
            'severity' => $this->severity,
            'severity_label' => ProjectRiskSeverityEnum::from($this->severity)->label(),
            'status' => $this->status,
            'status_label' => ProjectRiskStatusEnum::from($this->status)->label(),
            'milestone' => $this->whenLoaded('milestone', fn () => $this->milestone === null ? null : [
                'uuid' => $this->milestone->uuid,
                'title' => $this->milestone->title,
            ]),
            'all_team_members' => (bool) $this->all_team_members,
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($admin) => [
                'uuid' => $admin->uuid,
                'name' => $admin->displayName(),
            ])),
            'raised_by' => $this->whenLoaded('raisedByAdmin', fn () => $this->raisedByAdmin === null ? null : [
                'uuid' => $this->raisedByAdmin->uuid,
                'name' => $this->raisedByAdmin->displayName(),
            ]),
            'resolved_by' => $this->whenLoaded('resolvedByAdmin', fn () => $this->resolvedByAdmin === null ? null : [
                'uuid' => $this->resolvedByAdmin->uuid,
                'name' => $this->resolvedByAdmin->displayName(),
            ]),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
