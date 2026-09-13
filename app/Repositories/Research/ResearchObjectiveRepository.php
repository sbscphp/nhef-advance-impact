<?php

namespace App\Repositories\Research;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Research;
use App\Models\ResearchObjective;
use App\Repositories\Contracts\Research\ResearchObjectiveRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResearchObjectiveRepository implements ResearchObjectiveRepositoryInterface
{
    public function allForResearch(Research $research): Collection
    {
        return ResearchObjective::query()
            ->where('research_id', $research->id)
            ->with('assignments.admin')
            ->latest()
            ->get();
    }

    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ResearchObjective::query()
            ->where('research_id', $research->id)
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

    public function findByUuid(string $uuid): ?ResearchObjective
    {
        return ResearchObjective::query()->with('assignments.admin')->where('uuid', $uuid)->first();
    }

    public function create(Research $research, array $data, array $assigneeAdminIds): ResearchObjective
    {
        $objective = $research->objectives()->create($data);
        $this->syncAssignees($objective, $assigneeAdminIds);

        return $objective->refresh()->load('assignments.admin');
    }

    public function update(ResearchObjective $objective, array $data, ?array $assigneeAdminIds): ResearchObjective
    {
        $objective->forceFill($data)->save();

        if ($assigneeAdminIds !== null) {
            $this->syncAssignees($objective, $assigneeAdminIds);
        }

        return $objective->refresh()->load('assignments.admin');
    }

    public function delete(ResearchObjective $objective): void
    {
        $objective->delete();
    }

    /**
     * @param  list<int>  $assigneeAdminIds
     */
    private function syncAssignees(ResearchObjective $objective, array $assigneeAdminIds): void
    {
        $objective->assignments()->delete();
        foreach (array_unique($assigneeAdminIds) as $adminId) {
            $objective->assignments()->create(['admin_id' => $adminId]);
        }
    }
}
