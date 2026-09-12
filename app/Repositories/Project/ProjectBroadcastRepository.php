<?php

namespace App\Repositories\Project;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectBroadcast;
use App\Repositories\Contracts\Project\ProjectBroadcastRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectBroadcastRepository implements ProjectBroadcastRepositoryInterface
{
    public function paginateForProject(Project $project, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ProjectBroadcast::query()
            ->where('project_id', $project->id)
            ->with(['sender', 'recipients'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['delivery_via'] ?? null),
                fn ($query) => $query->where('delivery_via', $filters['filters']['delivery_via'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'send_date');

        ListingFilterRules::applySort($query, $filters, [
            'title' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'send_date', 'desc');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ProjectBroadcast
    {
        return ProjectBroadcast::query()->with(['sender', 'recipients'])->where('uuid', $uuid)->first();
    }

    public function create(Project $project, array $data, array $recipientAdminIds): ProjectBroadcast
    {
        $broadcast = $project->broadcasts()->create($data);
        $broadcast->recipients()->sync($recipientAdminIds);

        return $broadcast->load(['sender', 'recipients']);
    }

    public function update(ProjectBroadcast $broadcast, array $data, ?array $recipientAdminIds): ProjectBroadcast
    {
        $broadcast->forceFill($data)->save();

        if ($recipientAdminIds !== null) {
            $broadcast->recipients()->sync($recipientAdminIds);
        }

        return $broadcast->refresh()->load(['sender', 'recipients']);
    }
}
