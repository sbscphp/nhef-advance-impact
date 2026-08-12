<?php

namespace App\Repositories\Contracts\CampaignInstitution;

use App\Models\Campaign;
use App\Models\CampaignInstitution;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CampaignInstitutionRepositoryInterface
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(Campaign $campaign, array $rows): void;

    /**
     * Unpaginated; used for small per-campaign aggregations (e.g. the "View Campaign" header
     * total), not for the Institutions tab listing itself.
     *
     * @return Collection<int, CampaignInstitution>
     */
    public function allForCampaign(int $campaignId): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Campaign $campaign, array $data): CampaignInstitution;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator;

    public function findForCampaign(int $campaignId, string $uuid): ?CampaignInstitution;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CampaignInstitution $campaignInstitution, array $data): CampaignInstitution;

    public function delete(CampaignInstitution $campaignInstitution): void;
}
