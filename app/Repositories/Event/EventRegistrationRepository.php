<?php

namespace App\Repositories\Event;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\EventRegistration;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

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
}
