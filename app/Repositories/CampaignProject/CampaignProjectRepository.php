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
