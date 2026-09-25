<?php

namespace App\Http\Resources\Concerns;

use App\Models\Campaign;
use App\Models\CampaignProject;
use App\Support\Money;

/** @mixin Campaign */
trait PresentsCampaignSchedule
{
    /**
     * Start/end (ISO 8601 with time), the countdown lead time and the derived timer state.
     *
     * @return array<string, mixed>
     */
    protected function schedulePayload(): array
    {
        return [
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timer_lead_hours' => $this->timer_lead_hours,
            'timer' => $this->timer(),
            'display_status' => $this->displayStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectsPayload(bool $withDescription = true): array
    {
        if (! $this->relationLoaded('projects')) {
            return [];
        }

        return [
            'projects' => $this->projects->map(fn (CampaignProject $project): array => array_filter([
                'uuid' => $project->uuid,
                'name' => $project->name,
                'goal_amount' => (string) $project->goal_amount,
                'goal_amount_formatted' => Money::format($project->goal_amount, 'NGN'),
                'description' => $withDescription ? $project->description : false,
            ], fn ($value) => $value !== false))->values()->all(),
            'projects_total' => (string) $this->projects->sum('goal_amount'),
            'projects_total_formatted' => Money::format($this->projects->sum('goal_amount'), 'NGN'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function assigneePayload(): ?array
    {
        if (! $this->relationLoaded('allocatedAdmin') || $this->allocatedAdmin === null) {
            return null;
        }

        return [
            'admin_id' => $this->allocatedAdmin->uuid,
            'name' => $this->allocatedAdmin->displayName(),
        ];
    }
}
