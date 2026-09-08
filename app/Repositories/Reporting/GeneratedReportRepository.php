<?php

namespace App\Repositories\Reporting;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\GeneratedReport;
use App\Repositories\Contracts\Reporting\GeneratedReportRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GeneratedReportRepository implements GeneratedReportRepositoryInterface
{
    public function create(array $data): GeneratedReport
    {
        return GeneratedReport::create($data);
    }

    public function findByUuid(string $uuid): ?GeneratedReport
    {
        return GeneratedReport::query()
            ->with('creator')
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<GeneratedReport>
     */
    private function adminListQuery(array $filters): Builder
    {
        $query = GeneratedReport::query()
            ->with('creator')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['dataset'] ?? null),
                fn ($query) => $query->where('dataset', $filters['filters']['dataset'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'created_at');

        return $query;
    }
}
