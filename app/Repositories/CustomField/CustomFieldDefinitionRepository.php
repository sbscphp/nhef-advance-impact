<?php

namespace App\Repositories\CustomField;

use App\Enums\CustomFieldStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\CustomFieldDefinition;
use App\Repositories\Contracts\CustomField\CustomFieldDefinitionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomFieldDefinitionRepository implements CustomFieldDefinitionRepositoryInterface
{
    public function create(array $data): CustomFieldDefinition
    {
        return CustomFieldDefinition::create($data);
    }

    public function update(CustomFieldDefinition $definition, array $data): CustomFieldDefinition
    {
        $definition->fill($data)->save();

        return $definition;
    }

    public function findByUuid(string $uuid): ?CustomFieldDefinition
    {
        return CustomFieldDefinition::query()
            ->with('creator')
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)->paginate($perPage);
    }

    public function activeForModule(string $module): Collection
    {
        return CustomFieldDefinition::query()
            ->where('status', CustomFieldStatusEnum::ACTIVE->value)
            ->whereJsonContains('applicable_modules', $module)
            ->orderBy('name')
            ->get();
    }

    public function reportableForModule(string $module): Collection
    {
        return CustomFieldDefinition::query()
            ->where('status', CustomFieldStatusEnum::ACTIVE->value)
            ->where('show_in_reports', true)
            ->whereJsonContains('applicable_modules', $module)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CustomFieldDefinition>
     */
    private function adminListQuery(array $filters): Builder
    {
        $query = CustomFieldDefinition::query()
            ->with('creator')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            )
            ->when(
                filled($filters['filters']['applicable_module'] ?? null),
                fn ($query) => $query->whereJsonContains('applicable_modules', $filters['filters']['applicable_module'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'created_at');

        return $query;
    }
}
