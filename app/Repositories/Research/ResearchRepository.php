<?php

namespace App\Repositories\Research;

use App\Enums\ResearchStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Research;
use App\Repositories\Contracts\Research\ResearchRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ResearchRepository implements ResearchRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Research::query()
            ->with('creator')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Research
    {
        return Research::query()
            ->with([
                'creator',
                'objectives.assignments.admin',
                'milestones.assignments.admin',
                'deliverables.milestone', 'deliverables.assignments.admin',
            ])
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $data): Research
    {
        return Research::create($data);
    }

    public function update(Research $research, array $data): Research
    {
        $research->forceFill($data)->save();

        return $research->refresh();
    }

    public function countByStatus(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $scoped = fn (): Builder => $this->applyDateRange(Research::query(), 'created_at', $start, $end);

        return [
            'all' => (int) $scoped()->count(),
            'open' => (int) $scoped()->where('status', ResearchStatusEnum::OPEN->value)->count(),
            'completed' => (int) $scoped()->where('status', ResearchStatusEnum::COMPLETED->value)->count(),
        ];
    }

    private function applyDateRange(Builder $query, string $column, ?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        if ($start !== null) {
            $query->where($column, '>=', $start);
        }

        if ($end !== null) {
            $query->where($column, '<=', $end);
        }

        return $query;
    }
}
