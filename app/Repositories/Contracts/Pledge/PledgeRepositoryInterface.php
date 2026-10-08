<?php

namespace App\Repositories\Contracts\Pledge;

use App\Models\Pledge;
use Illuminate\Pagination\LengthAwarePaginator;

interface PledgeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Pledge;

    public function findByUuid(string $uuid): ?Pledge;

    public function findByUuidForUser(int $userId, string $uuid): ?Pledge;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Pledge $pledge, array $data): Pledge;

    public function incrementAmountPaid(Pledge $pledge, string $amount): Pledge;

    /**
     * @param  list<string>  $relations
     */
    public function loadFresh(Pledge $pledge, array $relations): Pledge;

    /**
     * Sum of amount_paid for a campaign's pledges from donors linked to the given tertiary
     * institution; feeds a National Giving Day campaign's per-institution progress.
     */
    public function sumReceivedForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): string;

    /**
     * @return array{count: int, total_pledged: string, total_fulfilled: string}
     */
    public function overviewForUser(int $userId): array;

    /**
     * Committed (not yet paid) NGN pledge value per donor's tertiary institution, excluding cancelled pledges.
     *
     * @param  list<int>  $tertiaryInstitutionIds
     * @return array<int, string>
     */
    public function totalCommittedByInstitutions(array $tertiaryInstitutionIds): array;

    /**
     * Pledge count and committed value (cancelled excluded) per campaign.
     *
     * @param  list<int>  $campaignIds
     * @return array<int, array{count: int, total: string}>
     */
    public function totalsByCampaigns(array $campaignIds): array;

    /**
     * Same as {@see self::totalsByCampaigns()} but narrowed to donors of one tertiary institution.
     *
     * @return array{count: int, total: string}
     */
    public function totalsForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): array;
}
