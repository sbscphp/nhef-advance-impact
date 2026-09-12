<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectExpenditure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectExpenditureRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectExpenditure>, 1: bool}
     */
    public function exportForProject(Project $project, array $filters, int $maxRows): array;

    /**
     * @return Collection<int, ProjectExpenditure>
     */
    public function allForProject(Project $project): Collection;

    public function findByUuid(string $uuid): ?ProjectExpenditure;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, ProjectBudgetLine $budgetLine, array $data): ProjectExpenditure;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProjectExpenditure $expenditure, array $data): ProjectExpenditure;

    public function delete(ProjectExpenditure $expenditure): void;

    /** Sum of every expenditure recorded on the project, across all its budget lines. */
    public function sumForProject(Project $project): string;
}
