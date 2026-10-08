<?php

namespace App\Repositories\Contracts\ConstituencyType;

use App\Models\ConstituencyType;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ConstituencyTypeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ConstituencyType;

    public function findByUuid(string $uuid): ?ConstituencyType;

    /**
     * @param  list<string>  $uuids
     * @return Collection<int, ConstituencyType>
     */
    public function findManyByUuids(array $uuids): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ConstituencyType $type, array $data): ConstituencyType;

    public function delete(ConstituencyType $type): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Same filters as {@see self::paginateForAdmin()} but capped instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ConstituencyType>, 1: bool}
     */
    public function exportForAdmin(array $filters): array;

    /** Number of users currently holding this type, for the list/detail screens. */
    public function usageCount(ConstituencyType $type): int;

    /**
     * Confers each of the given types on the user (idempotent: already-held types are untouched).
     */
    public function attachToUser(User $user, Collection $types, string $conferredByAdminUuid): void;

    public function detachFromUser(User $user, ConstituencyType $type): void;

    /**
     * @return Collection<int, ConstituencyType>
     */
    public function typesForUser(User $user): Collection;
}
