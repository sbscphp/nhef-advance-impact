<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublished(array $filters, int $perPage): LengthAwarePaginator;

    /** Matches by `uuid` or `slug`, so donor-facing "get event" routes work with either. */
    public function findPublishedByUuid(string $identifier): ?Event;

    public function incrementSeatsTaken(Event $event, int $quantity): Event;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Matches by `uuid` or `slug`, so admin event routes work with either. Unscoped by status;
     * admin "View Event" must load a deactivated/archived event too.
     */
    public function findByUuid(string $identifier): ?Event;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Event;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event;

    public function slugExists(string $slug): bool;

    /**
     * @return array{all: int, ongoing: int, completed: int, archived: int}
     */
    public function countByStatusBuckets(?CarbonInterface $start, ?CarbonInterface $end): array;
}
