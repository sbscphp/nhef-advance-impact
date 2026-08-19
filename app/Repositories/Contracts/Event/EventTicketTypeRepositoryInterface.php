<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use App\Models\EventTicketType;
use Illuminate\Support\Collection;

interface EventTicketTypeRepositoryInterface
{
    public function findByUuidForEvent(Event $event, string $uuid): ?EventTicketType;

    public function incrementQuantitySold(EventTicketType $ticketType, int $quantity): EventTicketType;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, EventTicketType>
     */
    public function createMany(Event $event, array $rows): Collection;

    /**
     * Full-list replace: creates/updates rows by `uuid` (when present) and deletes any
     * existing ticket type left out of `$rows`.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, EventTicketType>
     */
    public function syncForEvent(Event $event, array $rows): Collection;

    /**
     * @return Collection<int, EventTicketType>
     */
    public function allForEvent(Event $event): Collection;
}
