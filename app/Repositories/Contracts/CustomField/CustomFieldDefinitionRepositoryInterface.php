<?php

namespace App\Repositories\Contracts\CustomField;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Active definitions applicable to the given module; feeds a consumer's "which custom
     * fields apply to me" listing (e.g. a customer's own profile).
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function activeForModule(string $module): Collection;

    /**
     * Same as {@see self::activeForModule()}, further restricted to `show_in_reports`; feeds
     * the Reporting module's "Choose Custom Field" step.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function reportableForModule(string $module): Collection;
}
