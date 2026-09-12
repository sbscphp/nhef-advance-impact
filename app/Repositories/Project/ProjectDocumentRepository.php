<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Repositories\Contracts\Project\ProjectDocumentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectDocumentRepository implements ProjectDocumentRepositoryInterface
{
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->with('creator')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['category'] ?? null),
                fn ($query) => $query->where('category', $filters['filters']['category'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'created_at', 'desc');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ProjectDocument
    {
        return ProjectDocument::query()->with('creator')->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data): ProjectDocument
    {
        return $project->documents()->create($data);
    }

    public function delete(ProjectDocument $document): void
    {
        $document->delete();
    }
}
