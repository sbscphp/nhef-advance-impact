<?php

namespace App\Repositories\Event;

use App\Enums\EventStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Event;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRepository implements EventRepositoryInterface
{
    public function paginatePublished(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Event::query()
            ->published()
            ->with(['ticketTypes'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'starts_at', 'asc');

        return $query->paginate($perPage);
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
