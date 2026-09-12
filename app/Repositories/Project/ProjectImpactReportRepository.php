<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectImpactReport;
use App\Repositories\Contracts\Project\ProjectImpactReportRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectImpactReportRepository implements ProjectImpactReportRepositoryInterface
{
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectImpactReport::query()
            ->where('project_id', $project->id)
            ->with(['deliverable', 'budgetLine', 'creator'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['deliverable_uuid'] ?? null),
                fn ($query) => $query->whereHas('deliverable', fn ($d) => $d->where('uuid', $filters['filters']['deliverable_uuid']))
            )
            ->when(
                filled($filters['filters']['budget_line_uuid'] ?? null),
                fn ($query) => $query->whereHas('budgetLine', fn ($b) => $b->where('uuid', $filters['filters']['budget_line_uuid']))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'report_date');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'report_date', 'desc');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ProjectImpactReport
    {
        return ProjectImpactReport::query()->with(['deliverable', 'budgetLine', 'creator'])->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data): ProjectImpactReport
    {
        return $project->impactReports()->create($data);
    }

    public function update(ProjectImpactReport $report, array $data): ProjectImpactReport
    {
        $report->forceFill($data)->save();

        return $report->refresh();
    }

    public function delete(ProjectImpactReport $report): void
    {
        $report->delete();
    }
}
