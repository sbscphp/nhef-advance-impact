<?php

namespace App\Repositories\Contracts\Event;

use App\Models\EventRegistration;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRegistrationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EventRegistration;

    public function findByUuid(string $uuid): ?EventRegistration;

    public function findByUuidForUser(int $userId, string $uuid): ?EventRegistration;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventRegistration $registration, array $data): EventRegistration;

    /**
     * @param  list<string>  $relations
     */
    public function loadFresh(EventRegistration $registration, array $relations): EventRegistration;
}
