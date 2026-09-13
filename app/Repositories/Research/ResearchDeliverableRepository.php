<?php

namespace App\Repositories\Research;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Research;
use App\Models\ResearchDeliverable;
use App\Repositories\Contracts\Research\ResearchDeliverableRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResearchDeliverableRepository implements ResearchDeliverableRepositoryInterface
{
    public function allForResearch(Research $research): Collection
    {
        return ResearchDeliverable::query()->where('research_id', $research->id)->with('milestone')->get();
    }

    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ResearchDeliverable::query()
            ->where('research_id', $research->id)
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

    public function findByUuid(string $uuid): ?ResearchDeliverable
    {
        return ResearchDeliverable::query()->with(['milestone', 'assignments.admin', 'notifyRecipients'])->where('uuid', $uuid)->first();
    }

    public function create(Research $research, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ResearchDeliverable
    {
        $deliverable = $research->deliverables()->create($data);
        $this->syncAssignees($deliverable, $assigneeAdminIds);
        $deliverable->notifyRecipients()->sync($notifyRecipientAdminIds);

        return $deliverable->refresh()->load(['milestone', 'assignments.admin', 'notifyRecipients']);
    }

    public function update(ResearchDeliverable $deliverable, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ResearchDeliverable
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

    public function markComplete(ResearchDeliverable $deliverable): ResearchDeliverable
    {
        $deliverable->forceFill(['is_completed' => true, 'completed_at' => now()])->save();

        return $deliverable->refresh();
    }

    public function delete(ResearchDeliverable $deliverable): void
    {
        $deliverable->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ResearchDeliverable $deliverable, array $assigneeAdminIds): void
    {
        $deliverable->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $deliverable->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
