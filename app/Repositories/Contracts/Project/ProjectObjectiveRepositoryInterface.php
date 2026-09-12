<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectObjective;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectObjectiveRepositoryInterface
{
    /**
     * @return Collection<int, ProjectObjective>
     */
    public function allForProject(Project $project): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?ProjectObjective;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     */
    public function create(Project $project, array $data, array $assigneeAdminIds): ProjectObjective;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     */
    public function update(ProjectObjective $objective, array $data, ?array $assigneeAdminIds): ProjectObjective;

    public function delete(ProjectObjective $objective): void;
}
