<?php

namespace App\Http\Resources\Admin;

use App\Models\CampaignProject;
use App\Models\NationalGivingDayOpening;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Backs the admin "View National Giving Day" screen, the Opening-level equivalent of
 * CampaignDetailResource; every money/count figure is an aggregate across its Campaigns, see
 * CampaignService::openingDetailOverview(). Expects `donors_count`, `days_remaining` and
 * `overview` already set on the model, and `campaigns.campaignInstitutions.institution`/
 * `campaigns.projects` eager-loaded.
 *
 * @mixin NationalGivingDayOpening
 */
class NationalGivingDayOpeningDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $overview = $this->overview;
        $currency = $overview['currency'];

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'cover_media_type' => $this->cover_media_type,
            'currency' => $currency,
            // No goal_amount/progress_percentage: an opening has no one generic target, each
            // institution sets its own project goals independently.
            ...ViewerVisibility::money([
                'raised_amount' => (string) $overview['raised_amount'],
                'raised_amount_formatted' => Money::format($overview['raised_amount'], $currency),
            ]),
            'status' => $this->status,
            'donors_count' => $this->donors_count,
            'days_remaining' => $this->days_remaining,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'campaigns_count' => (int) $overview['campaigns_count'],
            'institutions_count' => (int) $overview['institutions_count'],
            'projects' => $this->whenLoaded('campaigns', fn () => $this->campaigns
                ->flatMap(fn ($campaign) => $campaign->projects)
                ->map(fn (CampaignProject $project): array => [
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'description' => $project->description,
                ])
                ->values()
                ->all()),
            'assigned_to' => $this->whenLoaded('allocatedAdmin', fn () => $this->allocatedAdmin === null ? null : [
                'admin_id' => $this->allocatedAdmin->uuid,
                'name' => $this->allocatedAdmin->displayName(),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'admin_id' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'overview' => [
                'donations_count' => $overview['donations_count'],
                'pledges_count' => $overview['pledges_count'],
                ...ViewerVisibility::money([
                    'amount_generated' => (string) $overview['raised_amount'],
                    'amount_generated_formatted' => Money::format($overview['raised_amount'], $currency),
                    'pledges_total' => (string) $overview['pledges_total'],
                    'pledges_total_formatted' => Money::format($overview['pledges_total'], $currency),
                ]),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
