<?php

namespace App\Repositories\Event;

use App\Enums\EventStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Event;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use Carbon\CarbonInterface;
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

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Event::query()
            ->with(['ticketTypes'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->whereDisplayStatus($filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'starts_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Event
    {
        return Event::query()
            ->with(['ticketTypes'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $data): Event
    {
        return Event::query()->create($data);
    }

    public function update(Event $event, array $data): Event
    {
        $event->fill($data)->save();

        return $event;
    }

    public function slugExists(string $slug): bool
    {
        return Event::query()->where('slug', $slug)->exists();
    }

    /** "Ongoing"/"completed" are derived from starts_at/ends_at (see {@see Event::displayStatus()}), not stored columns. */
    public function countByStatusBuckets(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $scoped = fn () => Event::query()
            ->when($start !== null, fn ($query) => $query->where('starts_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('starts_at', '<=', $end));

        $now = now();

        return [
            'all' => (int) $scoped()->count(),
            'ongoing' => (int) $scoped()
                ->where('status', EventStatusEnum::PUBLISHED->value)
                ->where('starts_at', '<=', $now)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->count(),
            'completed' => (int) $scoped()
                ->where('status', EventStatusEnum::PUBLISHED->value)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now)
                ->count(),
            'archived' => (int) $scoped()->where('status', EventStatusEnum::ARCHIVED->value)->count(),
        ];
    }
}
