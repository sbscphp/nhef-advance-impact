<?php

namespace App\Repositories\Event;

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
        return EventRegistration::query()
            ->with(['event', 'items.ticketType'])
            ->where('user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
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
