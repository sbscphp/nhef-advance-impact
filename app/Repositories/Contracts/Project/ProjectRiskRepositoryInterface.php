<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectRisk;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRiskRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, ProjectRisk>
     */
    public function allForProject(Project $project): Collection;

    public function findByUuid(string $uuid): ?ProjectRisk;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $recipientAdminIds
     */
    public function create(Project $project, array $data, array $recipientAdminIds): ProjectRisk;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $recipientAdminIds
     */
    public function update(ProjectRisk $risk, array $data, ?array $recipientAdminIds): ProjectRisk;

    public function markResolved(ProjectRisk $risk, string $resolvedByUuid): ProjectRisk;

    /** Distinct count of projects that currently have at least one open risk raised within the given window. */
    public function countDistinctProjectsWithOpenRisk(?CarbonInterface $start = null, ?CarbonInterface $end = null): int;

    /**
     * Every open risk across every project (for the org-wide "Issue: Action Required" panel),
     * most severe and most recent first.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateOpenAcrossProjects(array $filters, int $perPage): LengthAwarePaginator;
}
