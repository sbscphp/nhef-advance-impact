<?php

namespace App\Repositories\Event;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;

class EventTicketTypeRepository implements EventTicketTypeRepositoryInterface
{
    public function findByUuidForEvent(Event $event, string $uuid): ?EventTicketType
    {
        return EventTicketType::query()
            ->where('event_id', $event->id)
            ->where('uuid', $uuid)
            ->first();
    }

    public function incrementQuantitySold(EventTicketType $ticketType, int $quantity): EventTicketType
    {
        $ticketType->forceFill(['quantity_sold' => (int) $ticketType->quantity_sold + $quantity])->save();

        return $ticketType;
    }
}
