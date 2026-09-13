<?php

namespace App\Repositories\Contracts\Research;

use App\Models\Research;
use App\Models\ResearchObjective;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ResearchObjectiveRepositoryInterface
{
    /**
     * @return Collection<int, ResearchObjective>
     */
    public function allForResearch(Research $research): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?ResearchObjective;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     */
    public function create(Research $research, array $data, array $assigneeAdminIds): ResearchObjective;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     */
    public function update(ResearchObjective $objective, array $data, ?array $assigneeAdminIds): ResearchObjective;

    public function delete(ResearchObjective $objective): void;
}
