<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\CommunicationTask;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CommunicationTaskRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CommunicationTask;

    public function findByUuid(string $uuid): ?CommunicationTask;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CommunicationTask $task, array $data): CommunicationTask;

    public function delete(CommunicationTask $task): void;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Every instance of a recurring task (the root itself plus every generated child), newest
     * first, for the "Recurring History" panel.
     *
     * @return Collection<int, CommunicationTask>
     */
    public function listInstances(int $rootTaskId): Collection;

    /** The most recently due instance of a recurring task (self or latest child). */
    public function latestInstance(int $rootTaskId): ?CommunicationTask;

    /**
     * @param  int|null  $callLogId  When given, scopes the rollup to one call log's follow-ups.
     * @return array{total: int, upcoming: int, due_today: int, overdue: int}
     */
    public function overviewStats(?int $callLogId = null): array;
}
