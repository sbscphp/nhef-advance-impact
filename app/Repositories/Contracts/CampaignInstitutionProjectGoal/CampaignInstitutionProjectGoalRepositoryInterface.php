<?php

namespace App\Repositories\Contracts\CampaignInstitutionProjectGoal;

use App\Models\CampaignInstitution;

interface CampaignInstitutionProjectGoalRepositoryInterface
{
    /**
     * Full replace: deletes every existing project-goal row for this institution and inserts
     * the given set. `$rows` entries are `['campaign_project_id' => int, 'goal_amount' => string]`.
     *
     * @param  list<array{campaign_project_id: int, goal_amount: string}>  $rows
     */
    public function replaceForCampaignInstitution(CampaignInstitution $campaignInstitution, array $rows): void;

    public function sumForCampaignInstitution(int $campaignInstitutionId): string;
}
