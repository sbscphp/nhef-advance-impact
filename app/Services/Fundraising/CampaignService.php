<?php

namespace App\Services\Fundraising;

use App\Exceptions\ApiException;
use App\Models\Campaign;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignService
{
    public function __construct(private readonly CampaignRepositoryInterface $campaignRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->campaignRepository->paginateActive($filters, $perPage);
    }

    public function findActiveByUuid(string $uuid): Campaign
    {
        $campaign = $this->campaignRepository->findActiveByUuid($uuid);

        if (! $campaign instanceof Campaign) {
            throw new ApiException('Campaign not found.', 404);
        }

        return $campaign;
    }
}
