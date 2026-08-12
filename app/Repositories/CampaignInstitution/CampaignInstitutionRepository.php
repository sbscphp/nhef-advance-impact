<?php

namespace App\Repositories\CampaignInstitution;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Models\CampaignInstitution;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CampaignInstitutionRepository implements CampaignInstitutionRepositoryInterface
{
    public function createMany(Campaign $campaign, array $rows): void
    {
        foreach ($rows as $row) {
            $this->create($campaign, $row);
        }
    }

    public function create(Campaign $campaign, array $data): CampaignInstitution
    {
        return $campaign->campaignInstitutions()->create($data);
    }

    public function allForCampaign(int $campaignId): Collection
    {
        return CampaignInstitution::query()
            ->with('institution')
            ->where('campaign_id', $campaignId)
            ->get();
    }

    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CampaignInstitution::query()
            ->select('campaign_institutions.*')
            ->with(['institution', 'bankAccount.bank'])
            ->where('campaign_institutions.campaign_id', $campaignId)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('institution', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'campaign_institutions.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('institutions', 'institutions.id', '=', 'campaign_institutions.institution_id')
                ->orderBy('institutions.name', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('campaign_institutions.goal_amount', $direction),
        ], 'campaign_institutions.created_at');

        return $query->paginate($perPage);
    }

    public function findForCampaign(int $campaignId, string $uuid): ?CampaignInstitution
    {
        return CampaignInstitution::query()
            ->with(['institution', 'bankAccount.bank'])
            ->where('campaign_id', $campaignId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(CampaignInstitution $campaignInstitution, array $data): CampaignInstitution
    {
        $campaignInstitution->fill($data)->save();

        return $campaignInstitution;
    }

    public function delete(CampaignInstitution $campaignInstitution): void
    {
        $campaignInstitution->delete();
    }
}
