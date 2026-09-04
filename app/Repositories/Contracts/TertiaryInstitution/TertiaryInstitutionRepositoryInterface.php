<?php

namespace App\Repositories\Contracts\TertiaryInstitution;

use App\Models\TertiaryInstitution;
use Illuminate\Pagination\LengthAwarePaginator;

interface TertiaryInstitutionRepositoryInterface
{
    public function findByUuid(string $uuid): ?TertiaryInstitution;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Case-insensitive exact match if one exists, otherwise creates a new, unverified row so the
     * name is available for future search/select instead of being lost as a one-off free-text value.
     */
    public function findOrCreateByName(string $name): TertiaryInstitution;
}
