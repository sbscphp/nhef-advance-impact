<?php

namespace App\Repositories\Event;

use App\Enums\EventRegistrationStatusEnum;
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
}
