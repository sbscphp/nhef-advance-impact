<?php

namespace App\Repositories\Campaign;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\NationalGivingDayOpening;
use App\Repositories\Contracts\Campaign\NationalGivingDayOpeningRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NationalGivingDayOpeningRepository implements NationalGivingDayOpeningRepositoryInterface
{
    public function create(array $data): NationalGivingDayOpening
    {
        return NationalGivingDayOpening::query()->create($data);
    }

    public function findByUuid(string $uuid): ?NationalGivingDayOpening
    {
        return NationalGivingDayOpening::query()
            ->with(['allocatedAdmin', 'creator'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function slugExists(string $slug): bool
    {
        return NationalGivingDayOpening::query()->where('slug', $slug)->exists();
    }

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = NationalGivingDayOpening::query()
            ->with(['allocatedAdmin', 'creator'])
            ->withCount('campaigns')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filter_var($filters['filters']['upcoming_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query->where(function ($query) {
                    $now = now();
                    $query->where('ends_at', '>=', $now)
                        ->orWhere(function ($query) use ($now) {
                            $query->whereNull('ends_at')->where('starts_at', '>=', $now);
                        });
                })
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
        ], 'starts_at', 'desc');

        return $query->paginate($perPage);
    }
}
