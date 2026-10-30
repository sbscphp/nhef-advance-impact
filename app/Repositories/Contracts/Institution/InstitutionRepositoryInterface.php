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

    /**
     * Maps each given tertiary_institution_id to its onboarded Institution's own id (a 1:1
     * lookup). An id with no onboarded Institution row is absent from the result.
     *
     * @param  list<int>  $tertiaryInstitutionIds
     * @return array<int, int> tertiary_institution_id => institution_id
     */
    public function institutionIdsByTertiaryInstitutionIds(array $tertiaryInstitutionIds): array;

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
     * @return array{all: int, invite_sent: int, active: int, on_hold: int, access_revoked: int, completed: int}
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
