<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Repositories\Contracts\Project\ProjectDeliverableRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectDeliverableRepository implements ProjectDeliverableRepositoryInterface
{
    public function allForProject(Project $project): Collection
    {
        return ProjectDeliverable::query()->where('project_id', $project->id)->with('milestone')->get();
    }

    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectDeliverable::query()
            ->where('project_id', $project->id)
            ->with(['milestone', 'assignments.admin', 'notifyRecipients'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['milestone_uuid'] ?? null),
                fn ($query) => $query->whereHas('milestone', fn ($m) => $m->where('uuid', $filters['filters']['milestone_uuid']))
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
            'pending' => $query->where('is_completed', false)->where('due_at', '>=', now()->toDateString()),
            default => null,
        };
    }

    public function findByUuid(string $uuid): ?ProjectDeliverable
    {
        return ProjectDeliverable::query()->with(['milestone', 'assignments.admin', 'notifyRecipients'])->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ProjectDeliverable
    {
        $deliverable = $project->deliverables()->create($data);
        $this->syncAssignees($deliverable, $assigneeAdminIds);
        $deliverable->notifyRecipients()->sync($notifyRecipientAdminIds);

        return $deliverable->refresh()->load(['milestone', 'assignments.admin', 'notifyRecipients']);
    }

    public function update(ProjectDeliverable $deliverable, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ProjectDeliverable
    {
        $deliverable->forceFill($data)->save();

        if ($assigneeAdminIds !== null) {
            $this->syncAssignees($deliverable, $assigneeAdminIds);
        }

        if ($notifyRecipientAdminIds !== null) {
            $deliverable->notifyRecipients()->sync($notifyRecipientAdminIds);
        }

        return $deliverable->refresh()->load(['milestone', 'assignments.admin', 'notifyRecipients']);
    }

    public function markComplete(ProjectDeliverable $deliverable): ProjectDeliverable
    {
        $deliverable->forceFill(['is_completed' => true, 'completed_at' => now()])->save();

        return $deliverable->refresh();
    }

    public function delete(ProjectDeliverable $deliverable): void
    {
        $deliverable->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ProjectDeliverable $deliverable, array $assigneeAdminIds): void
    {
        $deliverable->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $deliverable->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
