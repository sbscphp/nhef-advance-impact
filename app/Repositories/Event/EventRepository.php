<?php

namespace App\Repositories\Event;

use App\Enums\EventStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Event;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    public function findPublishedByUuid(string $identifier): ?Event
    {
        return Event::query()
            ->with(['ticketTypes'])
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->where(fn ($query) => $query->where('uuid', $identifier)->orWhere('slug', $identifier))
            ->first();
    }

    public function incrementSeatsTaken(Event $event, int $quantity): Event
    {
        $event->forceFill(['seats_taken' => (int) $event->seats_taken + $quantity])->save();

        return $event;
    }

    /**
     * "Scheduled"/"ongoing"/"completed" are derived relative to now() (see
     * {@see Event::scopeWhereDisplayStatus()}), so a `starts_at` date window from the caller would
     * fight that derivation: e.g. a currently-ongoing event's starts_at is necessarily in the past,
     * so a forward-looking window would wrongly exclude it from the "ongoing" filter, and a
     * backward-looking one would wrongly exclude every "scheduled" (future) event. The date window
     * still applies to literal, non-derived statuses (draft/cancelled/deactivated/archived) and to
     * an unfiltered listing, where it legitimately narrows "events starting in this period".
     */
    private const NOW_RELATIVE_DISPLAY_STATUSES = ['scheduled', 'ongoing', 'completed'];

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $status = $filters['filters']['status'] ?? null;

        $query = Event::query()
            ->with(['ticketTypes', 'institution'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($status),
                fn ($query) => $query->whereDisplayStatus($status)
            );

        if (! in_array($status, self::NOW_RELATIVE_DISPLAY_STATUSES, true)) {
            ListingFilterRules::applyResolvedDateRange($query, $filters, 'starts_at');
        }

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $identifier): ?Event
    {
        return Event::query()
            ->with(['ticketTypes'])
            ->where(fn ($query) => $query->where('uuid', $identifier)->orWhere('slug', $identifier))
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
    public function countByStatusBuckets(): array
    {
        $now = now();

        return [
            'all' => (int) Event::query()->count(),
            'scheduled' => (int) Event::query()
                ->where('status', EventStatusEnum::PUBLISHED->value)
                ->where('starts_at', '>', $now)
                ->count(),
            'ongoing' => (int) Event::query()
                ->where('status', EventStatusEnum::PUBLISHED->value)
                ->where('starts_at', '<=', $now)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->count(),
            'completed' => (int) Event::query()
                ->where('status', EventStatusEnum::PUBLISHED->value)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now)
                ->count(),
            'archived' => (int) Event::query()->where('status', EventStatusEnum::ARCHIVED->value)->count(),
        ];
    }

    public function countActiveByInstitutions(array $institutionIds): array
    {
        if ($institutionIds === []) {
            return [];
        }

        $now = now();

        return Event::query()
            ->whereIn('institution_id', $institutionIds)
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->groupBy('institution_id')
            ->selectRaw('institution_id, count(*) as total')
            ->pluck('total', 'institution_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function dailyCountByCompletionStatus(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $now = now();

        $rows = Event::query()
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->whereBetween('starts_at', [$start, $end])
            ->groupBy('date')
            ->selectRaw(
                'DATE(starts_at) as date,'
                .' sum(case when ends_at is not null and ends_at < ? then 1 else 0 end) as completed,'
                .' sum(case when ends_at is null or ends_at >= ? then 1 else 0 end) as upcoming',
                [$now, $now]
            )
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($row) => (object) [
            'date' => (string) $row->date,
            'completed' => (int) $row->completed,
            'upcoming' => (int) $row->upcoming,
        ]);
    }

    public function idsByCompletionStatus(CarbonInterface $start, CarbonInterface $end): array
    {
        $now = now();

        $events = Event::query()
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->whereBetween('starts_at', [$start, $end])
            ->get(['id', 'ends_at']);

        return [
            'completed' => $events->filter(fn (Event $event) => $event->ends_at !== null && $event->ends_at->isPast())->pluck('id')->all(),
            'upcoming' => $events->filter(fn (Event $event) => $event->ends_at === null || ! $event->ends_at->isPast())->pluck('id')->all(),
        ];
    }
}
