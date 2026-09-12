<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Repositories\Contracts\Project\ProjectBudgetLineRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectBudgetLineRepository implements ProjectBudgetLineRepositoryInterface
{
    public function allForProject(Project $project): Collection
    {
        return ProjectBudgetLine::query()->where('project_id', $project->id)->with('recipients')->orderBy('title')->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ProjectBudgetLine>
     */
    private function buildProjectQuery(Project $project, array $filters): Builder
    {
        $query = ProjectBudgetLine::query()
            ->where('project_id', $project->id)
            ->with('recipients')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('amount_allocated', $direction),
        ], 'title', 'asc');

        return $query;
    }

    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->buildProjectQuery($project, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectBudgetLine>, 1: bool}
     */
    public function exportForProject(Project $project, array $filters, int $maxRows): array
    {
        $query = $this->buildProjectQuery($project, $filters);
        $total = (clone $query)->count();
        $rows = $query->limit($maxRows)->get();

        return [$rows, $total > $maxRows];
    }

    public function findByUuid(string $uuid): ?ProjectBudgetLine
    {
        return ProjectBudgetLine::query()->with('recipients')->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data, array $recipientAdminIds = []): ProjectBudgetLine
    {
        $budgetLine = $project->budgetLines()->create($data);
        $budgetLine->recipients()->sync($recipientAdminIds);

        return $budgetLine->refresh()->load('recipients');
    }

    public function update(ProjectBudgetLine $budgetLine, array $data, ?array $recipientAdminIds = null): ProjectBudgetLine
    {
        $budgetLine->forceFill($data)->save();

        if ($recipientAdminIds !== null) {
            $budgetLine->recipients()->sync($recipientAdminIds);
        }

        return $budgetLine->refresh()->load('recipients');
    }

    public function delete(ProjectBudgetLine $budgetLine): void
    {
        $budgetLine->delete();
    }

    public function sumUtilized(ProjectBudgetLine $budgetLine): string
    {
        return (string) $budgetLine->expenditures()->sum('amount');
    }

    public function referenceIdExists(string $referenceId): bool
    {
        return ProjectBudgetLine::query()->where('reference_id', $referenceId)->exists();
    }
}
