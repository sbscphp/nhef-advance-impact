<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectMilestoneRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, ProjectMilestone>
     */
    public function allForProject(Project $project): Collection;

    public function findByUuid(string $uuid): ?ProjectMilestone;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     * @param  list<int>  $notifyRecipientAdminIds
     */
    public function create(Project $project, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ProjectMilestone;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     * @param  list<int>|null  $notifyRecipientAdminIds
     */
    public function update(ProjectMilestone $milestone, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ProjectMilestone;

    public function markComplete(ProjectMilestone $milestone): ProjectMilestone;

    public function delete(ProjectMilestone $milestone): void;
}
