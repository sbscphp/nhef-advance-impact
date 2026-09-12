<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Repositories\Contracts\Project\ProjectRiskRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectRiskRepository implements ProjectRiskRepositoryInterface
{
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectRisk::query()
            ->where('project_id', $project->id)
            ->with(['milestone', 'raisedByAdmin', 'resolvedByAdmin', 'recipients'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['severity'] ?? null),
                fn ($query) => $query->where('severity', $filters['filters']['severity'])
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            )
            ->when(
                filled($filters['filters']['milestone_uuid'] ?? null),
                fn ($query) => $query->whereHas('milestone', fn ($m) => $m->where('uuid', $filters['filters']['milestone_uuid']))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'title' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'created_at', 'desc');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ProjectRisk
    {
        return ProjectRisk::query()
            ->with(['milestone', 'raisedByAdmin', 'resolvedByAdmin', 'recipients'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function allForProject(Project $project): Collection
    {
        return ProjectRisk::query()
            ->where('project_id', $project->id)
            ->with(['milestone', 'raisedByAdmin'])
            ->get();
    }

    public function create(Project $project, array $data, array $recipientAdminIds): ProjectRisk
    {
        $risk = $project->risks()->create($data);
        $risk->recipients()->sync($recipientAdminIds);

        return $risk->load(['milestone', 'raisedByAdmin', 'resolvedByAdmin', 'recipients']);
    }

    public function update(ProjectRisk $risk, array $data, ?array $recipientAdminIds): ProjectRisk
    {
        $risk->forceFill($data)->save();

        if ($recipientAdminIds !== null) {
            $risk->recipients()->sync($recipientAdminIds);
        }

        return $risk->refresh()->load(['milestone', 'raisedByAdmin', 'resolvedByAdmin', 'recipients']);
    }

    public function markResolved(ProjectRisk $risk, string $resolvedByUuid): ProjectRisk
    {
        $risk->forceFill([
            'status' => 'closed',
            'resolved_by' => $resolvedByUuid,
            'resolved_at' => now(),
        ])->save();

        return $risk->refresh()->load(['milestone', 'raisedByAdmin', 'resolvedByAdmin', 'recipients']);
    }

    public function countDistinctProjectsWithOpenRisk(?CarbonInterface $start = null, ?CarbonInterface $end = null): int
    {
        $query = ProjectRisk::query()->where('status', 'open');

        if ($start !== null) {
            $query->where('created_at', '>=', $start);
        }

        if ($end !== null) {
            $query->where('created_at', '<=', $end);
        }

        return $query->distinct('project_id')->count('project_id');
    }

    public function paginateOpenAcrossProjects(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectRisk::query()
            ->where('status', 'open')
            ->with(['project.managers', 'raisedByAdmin'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['severity'] ?? null),
                fn ($query) => $query->where('severity', $filters['filters']['severity'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
