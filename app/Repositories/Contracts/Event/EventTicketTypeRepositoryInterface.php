<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use App\Models\EventTicketType;

interface EventTicketTypeRepositoryInterface
{
    public function findByUuidForEvent(Event $event, string $uuid): ?EventTicketType;

    public function incrementQuantitySold(EventTicketType $ticketType, int $quantity): EventTicketType;
}
