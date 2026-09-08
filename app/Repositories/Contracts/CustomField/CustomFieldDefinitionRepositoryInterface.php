<?php

namespace App\Repositories\Contracts\CustomField;

use App\Models\CustomFieldDefinition;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomFieldDefinitionRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomFieldDefinition;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomFieldDefinition $definition, array $data): CustomFieldDefinition;

    public function findByUuid(string $uuid): ?CustomFieldDefinition;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;
}
