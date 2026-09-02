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
     * Sum of amount_paid for a campaign's pledges from donors whose profile `university` matches
     * the given institution name; feeds a National Giving Day campaign's per-institution progress.
     */
    public function sumReceivedForCampaignAndUniversity(int $campaignId, string $university): string;

    /**
     * @return array{count: int, total_pledged: string, total_fulfilled: string}
     */
    public function overviewForUser(int $userId): array;
}
