<?php

namespace App\Repositories\Contracts\Event;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublished(array $filters, int $perPage): LengthAwarePaginator;

    /** Matches by `uuid` or `slug`, so donor-facing "get event" routes work with either. */
    public function findPublishedByUuid(string $identifier): ?Event;

    public function incrementSeatsTaken(Event $event, int $quantity): Event;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Matches by `uuid` or `slug`, so admin event routes work with either. Unscoped by status;
     * admin "View Event" must load a deactivated/archived event too.
     */
    public function findByUuid(string $identifier): ?Event;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Event;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event;

    public function slugExists(string $slug): bool;

    /**
     * @return array{all: int, scheduled: int, ongoing: int, completed: int, archived: int}
     */
    public function countByStatusBuckets(?CarbonInterface $start, ?CarbonInterface $end): array;

    /**
     * Published events that haven't ended yet, per institution; feeds the public institution
     * directory's "No. of Active Events" card figure.
     *
     * @param  list<int>  $institutionIds
     * @return array<int, int>
     */
    public function countActiveByInstitutions(array $institutionIds): array;

    /**
     * Daily count of published events by starts_at date, split by whether the event has already
     * ended (as of now); feeds the dashboard's Event Intelligence "Distribution by Status" chart.
     * Carries Event's own tenant scope (all events for NHEF, one institution's own for its admin).
     *
     * @return Collection<int, object{date: string, completed: int, upcoming: int}>
     */
    public function dailyCountByCompletionStatus(CarbonInterface $start, CarbonInterface $end): Collection;

    /**
     * Scoped event ids (Event's own tenant scope) whose starts_at falls in the window, split by
     * whether they've already ended; feeds {@see EventRegistrationRepositoryInterface::dailyAttendanceByCompletionStatus()}.
     *
     * @return array{completed: list<int>, upcoming: list<int>}
     */
    public function idsByCompletionStatus(CarbonInterface $start, CarbonInterface $end): array;
}
