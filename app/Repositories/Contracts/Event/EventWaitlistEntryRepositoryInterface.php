<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use App\Models\EventWaitlistEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EventWaitlistEntryRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EventWaitlistEntry;

    public function nextPositionForEvent(Event $event): int;

    public function countForEvent(Event $event): int;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForEventAdmin(Event $event, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuidForEventAdmin(Event $event, string $uuid): ?EventWaitlistEntry;

    /**
     * Unpaginated, capped export set. Returns `[rows, truncated]`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EventWaitlistEntry>, 1: bool}
     */
    public function exportForEventAdmin(Event $event, array $filters, int $limit = 5000): array;
}
