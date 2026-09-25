<?php

namespace App\Repositories\Contracts\CampaignProject;

use App\Models\Campaign;
use App\Models\CampaignProject;
use Illuminate\Support\Collection;

interface CampaignProjectRepositoryInterface
{
    /**
     * @return Collection<int, CampaignProject>
     */
    public function allForCampaign(int $campaignId): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Campaign $campaign, array $data): CampaignProject;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CampaignProject $project, array $data): CampaignProject;

    public function delete(CampaignProject $project): void;
}
