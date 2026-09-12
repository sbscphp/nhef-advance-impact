<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Repositories\Contracts\Project\ProjectMilestoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectMilestoneRepository implements ProjectMilestoneRepositoryInterface
{
    public function allForProject(Project $project): Collection
    {
        return ProjectMilestone::query()->where('project_id', $project->id)->get();
    }

    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->with(['assignments.admin', 'notifyRecipients'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $this->applyStatusFilter($query, $filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'due_at', 'asc');

        return $query->paginate($perPage);
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'completed' => $query->where('is_completed', true),
            'overdue' => $query->where('is_completed', false)->where('due_at', '<', now()->toDateString()),
            'in_progress' => $query->where('is_completed', false)->where('due_at', '>=', now()->toDateString()),
            default => null,
        };
    }

    public function findByUuid(string $uuid): ?ProjectMilestone
    {
        return ProjectMilestone::query()->with(['assignments.admin', 'notifyRecipients'])->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ProjectMilestone
    {
        $milestone = $project->milestones()->create($data);
        $this->syncAssignees($milestone, $assigneeAdminIds);
        $milestone->notifyRecipients()->sync($notifyRecipientAdminIds);

        return $milestone->refresh()->load(['assignments.admin', 'notifyRecipients']);
    }

    public function update(ProjectMilestone $milestone, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ProjectMilestone
    {
        $milestone->forceFill($data)->save();

        if ($assigneeAdminIds !== null) {
            $this->syncAssignees($milestone, $assigneeAdminIds);
        }

        if ($notifyRecipientAdminIds !== null) {
            $milestone->notifyRecipients()->sync($notifyRecipientAdminIds);
        }

        return $milestone->refresh()->load(['assignments.admin', 'notifyRecipients']);
    }

    public function markComplete(ProjectMilestone $milestone): ProjectMilestone
    {
        $milestone->forceFill(['is_completed' => true, 'completion_percentage' => 100, 'completed_at' => now()])->save();

        return $milestone->refresh();
    }

    public function delete(ProjectMilestone $milestone): void
    {
        $milestone->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ProjectMilestone $milestone, array $assigneeAdminIds): void
    {
        $milestone->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $milestone->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
