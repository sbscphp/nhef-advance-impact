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

    public function slugExists(string $slug): bool;

    /**
     * @param  list<int>  $tertiaryInstitutionIds
     * @return list<int> the subset already linked to an institution
     */
    public function linkedTertiaryInstitutionIds(array $tertiaryInstitutionIds): array;

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

    /**
     * Onboarded, fully active institutions for the public "Join Your University Community"
     * directory; excludes invite_sent/on_hold/access_revoked/completed ones.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublic(array $filters, int $perPage): LengthAwarePaginator;
}
