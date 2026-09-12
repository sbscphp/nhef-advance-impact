<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectBudgetLine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectBudgetLineRepositoryInterface
{
    /**
     * @return Collection<int, ProjectBudgetLine>
     */
    public function allForProject(Project $project): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectBudgetLine>, 1: bool}
     */
    public function exportForProject(Project $project, array $filters, int $maxRows): array;

    public function findByUuid(string $uuid): ?ProjectBudgetLine;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $recipientAdminIds
     */
    public function create(Project $project, array $data, array $recipientAdminIds = []): ProjectBudgetLine;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $recipientAdminIds
     */
    public function update(ProjectBudgetLine $budgetLine, array $data, ?array $recipientAdminIds = null): ProjectBudgetLine;

    public function delete(ProjectBudgetLine $budgetLine): void;

    public function sumUtilized(ProjectBudgetLine $budgetLine): string;

    public function referenceIdExists(string $referenceId): bool;
}
