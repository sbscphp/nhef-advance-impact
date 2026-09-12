<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectExpenditure;
use App\Repositories\Contracts\Project\ProjectExpenditureRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectExpenditureRepository implements ProjectExpenditureRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ProjectExpenditure>
     */
    private function buildProjectQuery(Project $project, array $filters): Builder
    {
        $query = ProjectExpenditure::query()
            ->where('project_id', $project->id)
            ->with('budgetLine')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['budget_line_uuid'] ?? null),
                fn ($query) => $query->whereHas('budgetLine', fn ($b) => $b->where('uuid', $filters['filters']['budget_line_uuid']))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'transaction_date');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('amount', $direction),
        ], 'transaction_date', 'desc');

        return $query;
    }

    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->buildProjectQuery($project, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectExpenditure>, 1: bool}
     */
    public function exportForProject(Project $project, array $filters, int $maxRows): array
    {
        $query = $this->buildProjectQuery($project, $filters);
        $total = (clone $query)->count();
        $rows = $query->limit($maxRows)->get();

        return [$rows, $total > $maxRows];
    }

    public function findByUuid(string $uuid): ?ProjectExpenditure
    {
        return ProjectExpenditure::query()->with('budgetLine')->where('uuid', $uuid)->first();
    }

    public function allForProject(Project $project): Collection
    {
        return ProjectExpenditure::query()->where('project_id', $project->id)->with('budgetLine')->get();
    }

    public function create(Project $project, ProjectBudgetLine $budgetLine, array $data): ProjectExpenditure
    {
        return $project->expenditures()->create([...$data, 'budget_line_id' => $budgetLine->id]);
    }

    public function update(ProjectExpenditure $expenditure, array $data): ProjectExpenditure
    {
        $expenditure->forceFill($data)->save();

        return $expenditure->refresh();
    }

    public function delete(ProjectExpenditure $expenditure): void
    {
        $expenditure->delete();
    }

    public function sumForProject(Project $project): string
    {
        return (string) ProjectExpenditure::query()->where('project_id', $project->id)->sum('amount');
    }
}
