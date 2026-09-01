<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\CommunicationCallLog;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommunicationCallLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CommunicationCallLog;

    public function findByUuid(string $uuid): ?CommunicationCallLog;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function count(): int;

    /**
     * Distinct call logs with at least one follow-up task in each state, not a raw task count:
     * one call log with 3 upcoming follow-ups still counts as 1 "upcoming" call log, not 3.
     *
     * @return array{total: int, upcoming: int, due_today: int, overdue: int}
     */
    public function overviewStats(): array;
}
