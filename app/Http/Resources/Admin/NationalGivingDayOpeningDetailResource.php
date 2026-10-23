<?php

namespace App\Http\Resources\Admin;

use App\Models\CampaignProject;
use App\Models\NationalGivingDayOpening;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Backs the admin "View National Giving Day" screen - the Opening-level equivalent of
 * CampaignDetailResource. An Opening can have several Campaigns under it (one per institution
 * that set up its own scoped campaign for the shared day), so every money/count figure here is
 * an aggregate across all of them, not a single campaign's own numbers; see
 * CampaignService::openingDetailOverview(). The Institutions tab itself is a separate paginated
 * endpoint, same pattern as a single campaign's own Institutions tab.
 *
 * Expects `donors_count`, `days_remaining` and `overview` already set on the model (see
 * CampaignController@showOpening) and `campaigns.campaignInstitutions.institution`/
 * `campaigns.projects` eager-loaded (see CampaignService::findOpeningForAdmin()).
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
            // No goal_amount/progress_percentage here (2026-10-23, FE feedback): unlike a single
            // campaign, an opening has no one generic target to show - each institution sets its
            // own project goals independently, often before every institution under this opening
            // has even created its campaign yet, so there's nothing true to sum in advance.
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
