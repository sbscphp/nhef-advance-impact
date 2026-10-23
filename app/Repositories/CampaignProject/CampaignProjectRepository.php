<?php

namespace App\Repositories\CampaignProject;

use App\Models\Campaign;
use App\Models\CampaignProject;
use App\Repositories\Contracts\CampaignProject\CampaignProjectRepositoryInterface;
use Illuminate\Support\Collection;

class CampaignProjectRepository implements CampaignProjectRepositoryInterface
{
    public function allForCampaign(int $campaignId): Collection
    {
        return CampaignProject::query()->where('campaign_id', $campaignId)->orderBy('sort_order')->get();
    }

    public function allForOpening(int $openingId): Collection
    {
        return CampaignProject::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_projects.campaign_id')
            ->where('campaigns.national_giving_day_opening_id', $openingId)
            ->orderBy('campaign_projects.sort_order')
            ->select('campaign_projects.*')
            ->get();
    }

    public function create(Campaign $campaign, array $data): CampaignProject
    {
        return $campaign->projects()->create($data);
    }

    public function update(CampaignProject $project, array $data): CampaignProject
    {
        $project->fill($data)->save();

        return $project;
    }

    public function delete(CampaignProject $project): void
    {
        $project->delete();
    }
}
