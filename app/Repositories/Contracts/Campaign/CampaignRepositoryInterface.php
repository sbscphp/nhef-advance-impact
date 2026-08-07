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

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function findActiveByUuid(string $uuid): ?Campaign;

    /** Unscoped by status; for admin screens that must load a paused/draft/completed campaign too. */
    public function findByUuid(string $uuid): ?Campaign;

    public function incrementRaisedAmount(Campaign $campaign, string $amount): Campaign;

    public function slugExists(string $slug): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Campaign;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data): Campaign;

    /** Distinct donor count (registered users by id, guests by email) across the campaign's donations. */
    public function countDistinctDonors(Campaign $campaign): int;
}
