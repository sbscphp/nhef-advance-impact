<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use App\Models\EventRegistration;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EventRegistrationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EventRegistration;

    public function findByUuid(string $uuid): ?EventRegistration;

    public function findByUuidForUser(int $userId, string $uuid): ?EventRegistration;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventRegistration $registration, array $data): EventRegistration;

    /**
     * @param  list<string>  $relations
     */
    public function loadFresh(EventRegistration $registration, array $relations): EventRegistration;

    /**
     * Completed registrations ("Ticket Sales") for one event.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForEventAdmin(Event $event, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuidForEventAdmin(Event $event, string $uuid): ?EventRegistration;

    /**
     * Unpaginated, capped export set. Returns `[rows, truncated]`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EventRegistration>, 1: bool}
     */
    public function exportForEventAdmin(Event $event, array $filters, int $limit = 5000): array;

    /**
     * Daily count of tickets sold (completed registration items) within the window.
     *
     * @return Collection<int, object{date: string, quantity: int}>
     */
    public function salesTrend(Event $event, CarbonInterface $start, CarbonInterface $end): Collection;

    /**
     * @return Collection<int, EventRegistration>
     */
    public function completedForEvent(Event $event): Collection;
}
