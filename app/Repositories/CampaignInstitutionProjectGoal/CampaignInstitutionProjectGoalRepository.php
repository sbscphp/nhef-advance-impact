<?php

namespace App\Repositories\CampaignInstitutionProjectGoal;

use App\Models\CampaignInstitution;
use App\Models\CampaignInstitutionProjectGoal;
use App\Repositories\Contracts\CampaignInstitutionProjectGoal\CampaignInstitutionProjectGoalRepositoryInterface;

class CampaignInstitutionProjectGoalRepository implements CampaignInstitutionProjectGoalRepositoryInterface
{
    public function replaceForCampaignInstitution(CampaignInstitution $campaignInstitution, array $rows): void
    {
        $campaignInstitution->projectGoals()->delete();

        foreach ($rows as $row) {
            $campaignInstitution->projectGoals()->create($row);
        }
    }

    public function sumForCampaignInstitution(int $campaignInstitutionId): string
    {
        return (string) CampaignInstitutionProjectGoal::query()
            ->where('campaign_institution_id', $campaignInstitutionId)
            ->sum('goal_amount');
    }
}
