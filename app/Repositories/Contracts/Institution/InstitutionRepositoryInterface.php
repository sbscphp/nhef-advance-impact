<?php

namespace App\Repositories\Contracts\Institution;

use App\Models\Institution;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InstitutionRepositoryInterface
{
    /**
     * @return Collection<int, Institution>
     */
    public function all(bool $activeOnly): Collection;

    public function count(bool $activeOnly): int;

    public function findByUuid(string $uuid): ?Institution;

    public function nameExists(string $name): bool;

    public function emailExists(string $email): bool;

    public function existsForTertiaryInstitution(int $tertiaryInstitutionId, ?int $excludeInstitutionId = null): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Institution;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Institution $institution, array $data): Institution;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array{all: int, active: int, access_revoked: int}
     */
    public function countByStatus(?CarbonInterface $start, ?CarbonInterface $end): array;
}
