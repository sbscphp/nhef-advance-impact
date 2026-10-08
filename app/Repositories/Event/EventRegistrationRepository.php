<?php

namespace App\Repositories\Event;

use App\Enums\EventRegistrationStatusEnum;
use App\Enums\EventStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventRegistrationRepository implements EventRegistrationRepositoryInterface
{
    public function create(array $data): EventRegistration
    {
        return EventRegistration::create($data);
    }

    public function findByUuid(string $uuid): ?EventRegistration
    {
        return EventRegistration::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidForUser(int $userId, string $uuid): ?EventRegistration
    {
        return EventRegistration::query()
            ->with(['event', 'items.ticketType', 'payments'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = EventRegistration::query()
            ->select('event_registrations.*')
            ->with(['event', 'items.ticketType'])
            ->where('event_registrations.user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('event_registrations.status', $filters['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'event_registrations.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('events', 'events.id', '=', 'event_registrations.event_id')
                ->orderBy('events.title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('event_registrations.amount', $direction),
        ], 'event_registrations.created_at');

        return $query->paginate($perPage);
    }

    public function overviewForUser(int $userId): array
    {
        $now = now();

        $base = fn () => EventRegistration::query()
            ->join('events', 'events.id', '=', 'event_registrations.event_id')
            ->where('event_registrations.user_id', $userId)
            ->where('event_registrations.status', EventRegistrationStatusEnum::COMPLETED->value);

        $attended = (int) $base()->whereNotNull('events.ends_at')->where('events.ends_at', '<', $now)->count();
        $upcoming = (int) $base()->where(fn ($query) => $query->whereNull('events.ends_at')->orWhere('events.ends_at', '>=', $now))->count();

        return [
            'total' => $attended + $upcoming,
            'attended' => $attended,
            'upcoming' => $upcoming,
        ];
    }

    public function update(EventRegistration $registration, array $data): EventRegistration
    {
        $registration->forceFill($data)->save();

        return $registration;
    }

    public function loadFresh(EventRegistration $registration, array $relations): EventRegistration
    {
        return $registration->fresh($relations);
    }

    public function paginateForEventAdmin(Event $event, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->completedQueryForEvent($event)
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

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'completed_at');

        ListingFilterRules::applySort($query, $filters, [
            'value' => fn ($query, string $direction) => $query->orderBy('amount', $direction),
        ], 'completed_at');

        return $query->paginate($perPage);
    }

    public function findByUuidForEventAdmin(Event $event, string $uuid): ?EventRegistration
    {
        return EventRegistration::query()
            ->with(['user', 'items.ticketType', 'payments'])
            ->where('event_id', $event->id)
            ->where('uuid', $uuid)
            ->first();
    }

    public function exportForEventAdmin(Event $event, array $filters, int $limit = 5000): array
    {
        $query = $this->completedQueryForEvent($event);
        ListingFilterRules::applyResolvedDateRange($query, $filters, 'completed_at');

        $rows = $query->orderByDesc('completed_at')->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;

        return [$rows->take($limit), $truncated];
    }

    public function salesTrend(Event $event, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return DB::table('event_registration_items')
            ->join('event_registrations', 'event_registrations.id', '=', 'event_registration_items.event_registration_id')
            ->where('event_registrations.event_id', $event->id)
            ->where('event_registrations.status', EventRegistrationStatusEnum::COMPLETED->value)
            ->whereBetween('event_registrations.completed_at', [$start, $end])
            ->selectRaw('DATE(event_registrations.completed_at) as date, SUM(event_registration_items.quantity) as quantity')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function completedForEvent(Event $event): Collection
    {
        return $this->completedQueryForEvent($event)->with(['user'])->get();
    }

    private function completedQueryForEvent(Event $event)
    {
        return EventRegistration::query()
            ->with(['items.ticketType', 'payments'])
            ->where('event_id', $event->id)
            ->where('status', EventRegistrationStatusEnum::COMPLETED->value);
    }

    public function averageAttendanceForAdmin(): string
    {
        // Event::query() carries its own tenant scope (OwnedByInstitution), so this already
        // narrows to one institution's own events for an institution admin, all events for NHEF.
        $completedEventIds = Event::query()
            ->where('status', EventStatusEnum::PUBLISHED->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->pluck('id');

        if ($completedEventIds->isEmpty()) {
            return '0';
        }

        $totalRegistrations = EventRegistration::query()
            ->whereIn('event_id', $completedEventIds)
            ->where('status', EventRegistrationStatusEnum::COMPLETED->value)
            ->count();

        return bcdiv((string) $totalRegistrations, (string) $completedEventIds->count(), 2);
    }

    public function dailyAttendanceByCompletionStatus(array $completedEventIds, array $upcomingEventIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        if ($completedEventIds === [] && $upcomingEventIds === []) {
            return collect();
        }

        $completedIds = implode(',', array_map('intval', $completedEventIds)) ?: '-1';
        $upcomingIds = implode(',', array_map('intval', $upcomingEventIds)) ?: '-1';

        $rows = EventRegistration::query()
            ->whereIn('event_id', array_merge($completedEventIds, $upcomingEventIds))
            ->where('status', EventRegistrationStatusEnum::COMPLETED->value)
            ->whereBetween('completed_at', [$start, $end])
            ->groupBy('date')
            ->selectRaw(
                'DATE(completed_at) as date,'
                ." sum(case when event_id in ({$completedIds}) then 1 else 0 end) as completed,"
                ." sum(case when event_id in ({$upcomingIds}) then 1 else 0 end) as upcoming"
            )
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($row) => (object) [
            'date' => (string) $row->date,
            'completed' => (int) $row->completed,
            'upcoming' => (int) $row->upcoming,
        ]);
    }
}
