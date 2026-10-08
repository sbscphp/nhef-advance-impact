<?php

namespace App\Repositories\ConstituencyType;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\ConstituencyType;
use App\Models\User;
use App\Repositories\Contracts\ConstituencyType\ConstituencyTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ConstituencyTypeRepository implements ConstituencyTypeRepositoryInterface
{
    private const MAX_EXPORT_ROWS = 5000;

    public function create(array $data): ConstituencyType
    {
        return ConstituencyType::create($data);
    }

    public function findByUuid(string $uuid): ?ConstituencyType
    {
        return ConstituencyType::query()->where('uuid', $uuid)->first();
    }

    public function findManyByUuids(array $uuids): Collection
    {
        return ConstituencyType::query()->whereIn('uuid', $uuids)->get();
    }

    public function update(ConstituencyType $type, array $data): ConstituencyType
    {
        $type->fill($data)->save();

        return $type;
    }

    public function delete(ConstituencyType $type): bool
    {
        return (bool) $type->delete();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ConstituencyType>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        $query = $this->adminListQuery($filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ConstituencyType>
     */
    private function adminListQuery(array $filters): Builder
    {
        $query = ConstituencyType::query()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('is_active', $filters['filters']['status'] === 'active')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'updated_at');

        return $query;
    }

    public function usageCount(ConstituencyType $type): int
    {
        return $type->users()->count();
    }

    public function attachToUser(User $user, Collection $types, string $conferredByAdminUuid): void
    {
        $pivotData = [];
        foreach ($types as $type) {
            $pivotData[$type->id] = [
                'conferred_by' => $conferredByAdminUuid,
                'conferred_at' => now(),
            ];
        }

        $user->constituencyTypes()->syncWithoutDetaching($pivotData);
    }

    public function detachFromUser(User $user, ConstituencyType $type): void
    {
        $user->constituencyTypes()->detach($type->id);
    }

    public function typesForUser(User $user): Collection
    {
        return $user->constituencyTypes;
    }
}
