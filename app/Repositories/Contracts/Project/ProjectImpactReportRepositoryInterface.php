<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectImpactReport;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectImpactReportRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?ProjectImpactReport;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data): ProjectImpactReport;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProjectImpactReport $report, array $data): ProjectImpactReport;

    public function delete(ProjectImpactReport $report): void;
}
