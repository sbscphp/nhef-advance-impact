<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectDeliverable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectDeliverableRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, ProjectDeliverable>
     */
    public function allForProject(Project $project): Collection;

    public function findByUuid(string $uuid): ?ProjectDeliverable;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     * @param  list<int>  $notifyRecipientAdminIds
     */
    public function create(Project $project, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ProjectDeliverable;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     * @param  list<int>|null  $notifyRecipientAdminIds
     */
    public function update(ProjectDeliverable $deliverable, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ProjectDeliverable;

    public function markComplete(ProjectDeliverable $deliverable): ProjectDeliverable;

    public function delete(ProjectDeliverable $deliverable): void;
}
