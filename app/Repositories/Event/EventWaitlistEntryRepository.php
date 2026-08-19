<?php

namespace App\Repositories\Event;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Event;
use App\Models\EventWaitlistEntry;
use App\Repositories\Contracts\Event\EventWaitlistEntryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EventWaitlistEntryRepository implements EventWaitlistEntryRepositoryInterface
{
    public function create(array $data): EventWaitlistEntry
    {
        return EventWaitlistEntry::query()->create($data);
    }

    public function nextPositionForEvent(Event $event): int
    {
        return 1 + (int) EventWaitlistEntry::query()->where('event_id', $event->id)->max('position');
    }

    public function countForEvent(Event $event): int
    {
        return EventWaitlistEntry::query()->where('event_id', $event->id)->count();
    }

    public function paginateForEventAdmin(Event $event, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->queryForEvent($event)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $term = '%'.$filters['search'].'%';
                    $query->where('guest_name', 'like', $term)
                        ->orWhere('guest_email', 'like', $term)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('firstname', 'like', $term)
                            ->orWhere('lastname', 'like', $term)
                            ->orWhere('email', 'like', $term));
                })
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'value' => fn ($query, string $direction) => $query->orderBy('projected_value', $direction),
        ], 'position', 'asc');

        return $query->paginate($perPage);
    }

    public function findByUuidForEventAdmin(Event $event, string $uuid): ?EventWaitlistEntry
    {
        return EventWaitlistEntry::query()
            ->with(['user', 'ticketType'])
            ->where('event_id', $event->id)
            ->where('uuid', $uuid)
            ->first();
    }

    public function exportForEventAdmin(Event $event, array $filters, int $limit = 5000): array
    {
        $query = $this->queryForEvent($event);
        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        $rows = $query->orderBy('position')->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;

        return [$rows->take($limit), $truncated];
    }

    private function queryForEvent(Event $event)
    {
        return EventWaitlistEntry::query()
            ->with(['user', 'ticketType'])
            ->where('event_id', $event->id);
    }
}
