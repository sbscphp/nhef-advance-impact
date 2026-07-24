<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublished(array $filters, int $perPage): LengthAwarePaginator;

    public function findPublishedByUuid(string $uuid): ?Event;

    public function incrementSeatsTaken(Event $event, int $quantity): Event;
}
