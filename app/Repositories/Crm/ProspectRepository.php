<?php

namespace App\Repositories\Crm;

use App\Enums\ProspectPipelineStageEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Prospect;
use App\Repositories\Contracts\Crm\ProspectRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProspectRepository implements ProspectRepositoryInterface
{
    public function create(array $data): Prospect
    {
        return Prospect::create($data);
    }

    public function findByUuid(string $uuid): ?Prospect
    {
        return Prospect::query()
            ->with(['assignedAdmin', 'creator'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(Prospect $prospect, array $data): Prospect
    {
        $prospect->forceFill($data)->save();

        return $prospect;
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Prospect::query()
            ->with(['assignedAdmin', 'creator'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $term = '%'.$filters['search'].'%';
                    $query->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                })
            )
            ->when(
                filled($filters['filters']['stage'] ?? null),
                fn ($query) => $query->where('stage', $filters['filters']['stage'])
            )
            ->when(
                filled($filters['filters']['assigned_admin_id'] ?? null),
                fn ($query) => $query->whereHas('assignedAdmin', fn ($query) => $query->where('uuid', $filters['filters']['assigned_admin_id']))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('first_name', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('estimated_value', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function kanban(): Collection
    {
        $prospects = Prospect::query()
            ->with(['assignedAdmin'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('stage');

        return collect(ProspectPipelineStageEnum::values())
            ->mapWithKeys(fn (string $stage) => [$stage => $prospects->get($stage, collect())]);
    }
}
