<?php

namespace App\Repositories\Contracts\DonorTier;

use App\Models\DonorTier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DonorTierRepositoryInterface
{
    /**
     * Deactivated tiers are skipped everywhere a donor's tier is resolved. Institution-scoped
     * (2026-10-22): returns that institution's own active tiers, or the platform-wide default
     * set (institution_id null) if it has none configured. Pass null for the default set itself.
     *
     * @return Collection<int, DonorTier>
     */
    public function activeOrderedByThreshold(?int $institutionId = null): Collection;

    /**
     * Same as {@see self::activeOrderedByThreshold()} but for many institutions in one query -
     * for batch tier resolution across a page of donors spanning several institutions.
     *
     * @param  list<int>  $institutionIds
     * @return array<int, Collection<int, DonorTier>> Keyed by institution_id; an id with no
     *                                                 tiers of its own is not present in the
     *                                                 result, callers fall back to the default
     *                                                 set themselves.
     */
    public function activeOrderedByThresholdForInstitutions(array $institutionIds): array;

    /**
     * The highest active tier whose minimum_amount the given lifetime total meets or exceeds,
     * or null if the total doesn't qualify for even the lowest active tier. Institution-scoped
     * the same way as {@see self::activeOrderedByThreshold()}.
     */
    public function findForAmount(string $amount, ?int $institutionId = null): ?DonorTier;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DonorTier;

    public function findByUuid(string $uuid): ?DonorTier;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DonorTier $tier, array $data): DonorTier;

    public function delete(DonorTier $tier): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Same filters as {@see self::paginateForAdmin()} but capped instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonorTier>, 1: bool}
     */
    public function exportForAdmin(array $filters): array;

    /**
     * Count of distinct donors and distinct institutions whose lifetime NGN total falls in
     * [$minimum, $maximum) ($maximum null means unbounded above, i.e. the highest tier).
     * $tertiaryInstitutionId scopes to one institution's own donors (a tier belongs to one
     * institution now); null keeps the old platform-wide count, for a default tier.
     *
     * @return array{alumni_count: int, institution_count: int}
     */
    public function statsForRange(string $minimum, ?string $maximum, ?int $tertiaryInstitutionId = null): array;

    /**
     * Donors whose lifetime NGN total falls in [$minimum, $maximum), with their donation count
     * and lifetime total attached, for the tier's "Alumni List" tab. $tertiaryInstitutionId same
     * meaning as {@see self::statsForRange()}.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateUsersInRange(string $minimum, ?string $maximum, array $filters, int $perPage, ?int $tertiaryInstitutionId = null): LengthAwarePaginator;

    /**
     * Same filters/bracket as {@see self::paginateUsersInRange()} but capped instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportUsersInRange(string $minimum, ?string $maximum, array $filters, ?int $tertiaryInstitutionId = null): array;
}
