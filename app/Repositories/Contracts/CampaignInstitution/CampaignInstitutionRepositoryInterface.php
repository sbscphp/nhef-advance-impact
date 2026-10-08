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

    /**
     * Campaigns one institution participates in, for the Institution detail "Campaign" tab.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForInstitution(int $institutionId, array $filters, int $perPage): LengthAwarePaginator;

    public function findForCampaign(int $campaignId, string $uuid): ?CampaignInstitution;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CampaignInstitution $campaignInstitution, array $data): CampaignInstitution;

    public function delete(CampaignInstitution $campaignInstitution): void;

    /**
     * @param  list<int>  $institutionIds
     * @return array<int, int> institution id => number of campaigns it participates in
     */
    public function countByInstitutions(array $institutionIds): array;

    /**
     * Same as {@see self::countByInstitutions()} but counting only currently active campaigns;
     * feeds the public institution directory's "Active Campaign" card figure.
     *
     * @param  list<int>  $institutionIds
     * @return array<int, int>
     */
    public function countActiveByInstitutions(array $institutionIds): array;

    /**
     * @param  list<int>  $campaignIds
     * @return array<int, int>
     */
    public function countByCampaigns(array $campaignIds): array;

    /**
     * Participating institution uuid/name per campaign, for the campaign list's "institutions"
     * column - a standard campaign has exactly one, a National Giving Day campaign may have many.
     *
     * @param  list<int>  $campaignIds
     * @return array<int, list<array{uuid: string, name: string}>>
     */
    public function namesByCampaigns(array $campaignIds): array;

    /**
     * Every row for a campaign with its bank account, unpaginated, for exporting the Institutions tab.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CampaignInstitution>
     */
    public function exportForCampaign(int $campaignId, array $filters): Collection;

    /**
     * One row per institution on every active campaign that overlaps the window (or all active
     * campaigns when none is given), with the campaign and institution loaded.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateActiveTracking(array $filters, int $perPage): LengthAwarePaginator;
}
