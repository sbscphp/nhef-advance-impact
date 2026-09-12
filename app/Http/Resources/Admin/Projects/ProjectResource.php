<?php

namespace App\Http\Resources\Admin\Projects;

use App\Enums\ProjectCategoryEnum;
use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
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
            'category' => $this->category,
            'category_label' => $this->category !== null ? ProjectCategoryEnum::from($this->category)->label() : null,
            'status' => $this->status,
            'status_label' => ProjectStatusEnum::from($this->status)->label(),
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'country' => $this->country,
            'state_lga' => $this->state_lga,
            'address' => $this->address,
            'approved_budget' => (string) $this->approved_budget,
            'approved_budget_formatted' => Money::format($this->approved_budget, $this->currency),
            'budget_min' => $this->budget_min !== null ? (string) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (string) $this->budget_max : null,
            'funding_received' => (string) $this->funding_received,
            'funding_received_formatted' => Money::format($this->funding_received, $this->currency),
            'currency' => $this->currency,
            'budget_overview' => $this->when(isset($this->budget_overview), fn () => [
                'approved_budget' => $this->budget_overview['approved_budget'],
                'approved_budget_formatted' => Money::format($this->budget_overview['approved_budget'], $this->currency),
                'funding_received' => $this->budget_overview['funding_received'],
                'funding_received_formatted' => Money::format($this->budget_overview['funding_received'], $this->currency),
                'total_utilized' => $this->budget_overview['total_utilized'],
                'total_utilized_formatted' => Money::format($this->budget_overview['total_utilized'], $this->currency),
                'remaining_budget' => $this->budget_overview['remaining_budget'],
                'remaining_budget_formatted' => Money::format($this->budget_overview['remaining_budget'], $this->currency),
            ]),
            'funded_by_donor' => (bool) $this->funded_by_donor,
            'funded_by_donation' => (bool) $this->funded_by_donation,
            'funding_donors' => $this->whenLoaded('fundingDonors', fn () => $this->fundingDonors->map(fn ($user) => [
                'uuid' => $user->uuid,
                'name' => $user->displayName(),
            ])),
            'funding_campaigns' => $this->whenLoaded('fundingCampaigns', fn () => $this->fundingCampaigns->map(fn ($campaign) => [
                'uuid' => $campaign->uuid,
                'title' => $campaign->title,
            ])),
            'team_members' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->map(fn ($admin) => [
                'uuid' => $admin->uuid,
                'name' => $admin->displayName(),
                'role_title' => $admin->pivot->role_title,
                'is_manager' => (bool) $admin->pivot->is_manager,
            ])),
            'managers' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers
                ->filter(fn ($admin) => (bool) $admin->pivot->is_manager)
                ->values()
                ->map(fn ($admin) => [
                    'uuid' => $admin->uuid,
                    'name' => $admin->displayName(),
                ])),
            'objectives' => $this->whenLoaded('objectives', fn () => ProjectObjectiveResource::collection($this->objectives)),
            'milestones' => $this->whenLoaded('milestones', fn () => ProjectMilestoneResource::collection($this->milestones)),
            'deliverables' => $this->whenLoaded('deliverables', fn () => ProjectDeliverableResource::collection($this->deliverables)),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
