<?php

namespace App\Repositories\Contracts\User;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUuid(string $uuid): ?User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    /**
     * Admin usage only.
     */
    public function all(): Collection;

    /**
     * Alumni matching by name, email, or organisation. Used for the admin "Search & Select
     * Alumni" flow (adding people to a Networking Community/Forum) and the customer-facing
     * alumni directory used to start a direct message; pass `exclude_user_id` to omit the viewer
     * from their own results.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateAlumniSearch(array $filters, int $perPage): LengthAwarePaginator;

    public function emailExists(string $email): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array{all: int, active: int, access_revoked: int}
     */
    public function countByStatus(?CarbonInterface $start, ?CarbonInterface $end): array;

    /**
     * Same filters as {@see self::paginateForAdmin()} but capped instead of paginated, for
     * CSV/PDF export of the Alumni Management list.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportForAdmin(array $filters): array;

    /**
     * Constituent picker for Communications: search plus tertiary institution/department/
     * graduation-year segmentation, all optional and AND'd together.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForSegment(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Every user matching the given segment criteria, unpaginated, for resolving a mail's
     * recipient list. Only `id` and `email` are selected.
     *
     * @param  array<string, mixed>  $segment
     * @return Collection<int, User>
     */
    public function resolveSegmentMembers(array $segment): Collection;

    /**
     * @param  list<string>  $uuids
     * @return Collection<int, User>
     */
    public function findManyByUuids(array $uuids): Collection;
}
