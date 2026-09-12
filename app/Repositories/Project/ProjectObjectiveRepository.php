<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectObjective;
use App\Repositories\Contracts\Project\ProjectObjectiveRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectObjectiveRepository implements ProjectObjectiveRepositoryInterface
{
    public function allForProject(Project $project): Collection
    {
        return ProjectObjective::query()
            ->where('project_id', $project->id)
            ->with('assignments.admin')
            ->latest()
            ->get();
    }

    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectObjective::query()
            ->where('project_id', $project->id)
            ->with('assignments.admin')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                isset($filters['filters']['is_completed']),
                fn ($query) => $query->where('is_completed', filter_var($filters['filters']['is_completed'], FILTER_VALIDATE_BOOLEAN))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'created_at', 'desc');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ProjectObjective
    {
        return ProjectObjective::query()->with('assignments.admin')->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data, array $assigneeAdminIds): ProjectObjective
    {
        $objective = $project->objectives()->create($data);
        $this->syncAssignees($objective, $assigneeAdminIds);

        return $objective->refresh()->load('assignments.admin');
    }

    public function update(ProjectObjective $objective, array $data, ?array $assigneeAdminIds): ProjectObjective
    {
        $objective->forceFill($data)->save();

        if ($assigneeAdminIds !== null) {
            $this->syncAssignees($objective, $assigneeAdminIds);
        }

        return $objective->refresh()->load('assignments.admin');
    }

    public function delete(ProjectObjective $objective): void
    {
        $objective->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ProjectObjective $objective, array $assigneeAdminIds): void
    {
        $objective->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $objective->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
