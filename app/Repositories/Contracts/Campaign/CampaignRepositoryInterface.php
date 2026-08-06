<?php

namespace App\Repositories\Contracts\Campaign;

use App\Models\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateActive(array $filters, int $perPage): LengthAwarePaginator;

    public function findActiveByUuid(string $uuid): ?Campaign;

    public function incrementRaisedAmount(Campaign $campaign, string $amount): Campaign;

    public function slugExists(string $slug): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Campaign;
}
