<?php

namespace App\Repositories\Contracts\Donation;

use App\Models\Donation;
use Illuminate\Pagination\LengthAwarePaginator;

interface DonationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Donation;

    public function findByUuid(string $uuid): ?Donation;

    public function findByUuidForUser(int $userId, string $uuid): ?Donation;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Donation $donation, array $data): Donation;

    public function incrementTotalReceived(Donation $donation, string $amount): Donation;

    /**
     * @param  list<string>  $relations
     */
    public function loadFresh(Donation $donation, array $relations): Donation;
}
