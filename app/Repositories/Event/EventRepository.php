<?php

namespace App\Repositories\Event;

use App\Enums\EventStatusEnum;
use App\Models\Event;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRepository implements EventRepositoryInterface
{
    public function paginatePublished(array $filters, int $perPage): LengthAwarePaginator
    {
        return Event::query()
            ->published()
            ->with(['ticketTypes'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->orderBy('starts_at')
            ->paginate($perPage);
    }

    public function findPublishedByUuid(string $uuid): ?Event
    {
        return Event::query()
            ->with(['ticketTypes'])
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->where('uuid', $uuid)
            ->first();
    }

    public function incrementSeatsTaken(Event $event, int $quantity): Event
    {
        $event->forceFill(['seats_taken' => (int) $event->seats_taken + $quantity])->save();

        return $event;
    }
}
