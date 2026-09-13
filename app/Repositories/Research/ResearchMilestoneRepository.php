<?php

namespace App\Repositories\Research;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Research;
use App\Models\ResearchMilestone;
use App\Repositories\Contracts\Research\ResearchMilestoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResearchMilestoneRepository implements ResearchMilestoneRepositoryInterface
{
    public function allForResearch(Research $research): Collection
    {
        return ResearchMilestone::query()->where('research_id', $research->id)->get();
    }

    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ResearchMilestone::query()
            ->where('research_id', $research->id)
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

    public function findByUuid(string $uuid): ?ResearchMilestone
    {
        return ResearchMilestone::query()->with(['assignments.admin', 'notifyRecipients'])->where('uuid', $uuid)->first();
    }

    public function create(Research $research, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ResearchMilestone
    {
        $milestone = $research->milestones()->create($data);
        $this->syncAssignees($milestone, $assigneeAdminIds);
        $milestone->notifyRecipients()->sync($notifyRecipientAdminIds);

        return $milestone->refresh()->load(['assignments.admin', 'notifyRecipients']);
    }

    public function update(ResearchMilestone $milestone, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ResearchMilestone
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

    public function markComplete(ResearchMilestone $milestone): ResearchMilestone
    {
        $milestone->forceFill(['is_completed' => true, 'completion_percentage' => 100, 'completed_at' => now()])->save();

        return $milestone->refresh();
    }

    public function delete(ResearchMilestone $milestone): void
    {
        $milestone->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ResearchMilestone $milestone, array $assigneeAdminIds): void
    {
        $milestone->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $milestone->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
