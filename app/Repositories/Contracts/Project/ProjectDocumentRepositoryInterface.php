<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectDocumentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?ProjectDocument;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data): ProjectDocument;

    public function delete(ProjectDocument $document): void;
}
