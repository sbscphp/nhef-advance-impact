<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectBroadcast;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectBroadcastRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?ProjectBroadcast;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $recipientAdminIds
     */
    public function create(Project $project, array $data, array $recipientAdminIds): ProjectBroadcast;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $recipientAdminIds
     */
    public function update(ProjectBroadcast $broadcast, array $data, ?array $recipientAdminIds): ProjectBroadcast;
}
