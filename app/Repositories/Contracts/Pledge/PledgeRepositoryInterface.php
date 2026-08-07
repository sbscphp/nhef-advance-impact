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
}
