<?php

namespace App\Repositories\Event;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;
use Illuminate\Support\Collection;

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

    public function createMany(Event $event, array $rows): Collection
    {
        $created = new Collection;

        foreach ($rows as $sortOrder => $row) {
            $row['sort_order'] = $row['sort_order'] ?? $sortOrder;
            $created->push($event->ticketTypes()->create($row));
        }

        return $created;
    }

    public function syncForEvent(Event $event, array $rows): Collection
    {
        $existing = $this->allForEvent($event)->keyBy('uuid');
        $keptUuids = [];

        foreach ($rows as $sortOrder => $row) {
            $uuid = $row['uuid'] ?? null;
            unset($row['uuid']);
            $row['sort_order'] = $row['sort_order'] ?? $sortOrder;

            if ($uuid !== null && $existing->has($uuid)) {
                $ticketType = $existing->get($uuid);
                $ticketType->fill($row)->save();
                $keptUuids[] = $uuid;

                continue;
            }

            $created = $event->ticketTypes()->create($row);
            $keptUuids[] = $created->uuid;
        }

        foreach ($existing as $uuid => $ticketType) {
            if (! in_array($uuid, $keptUuids, true)) {
                $ticketType->delete();
            }
        }

        return $this->allForEvent($event);
    }

    public function allForEvent(Event $event): Collection
    {
        return $event->ticketTypes()->orderBy('sort_order')->get();
    }
}
