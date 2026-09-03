<?php

namespace App\Repositories\Contracts\DonorTier;

use App\Models\DonorTier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DonorTierRepositoryInterface
{
    /**
     * @return Collection<int, DonorTier>
     */
    public function allOrderedByThreshold(): Collection;

    /**
     * The highest tier whose minimum_amount the given lifetime total meets or exceeds, or
     * null if the total doesn't qualify for even the lowest tier.
     */
    public function findForAmount(string $amount): ?DonorTier;

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
     *
     * @return array{alumni_count: int, institution_count: int}
     */
    public function statsForRange(string $minimum, ?string $maximum): array;

    /**
     * Donors whose lifetime NGN total falls in [$minimum, $maximum), with their donation count
     * and lifetime total attached, for the tier's "Alumni List" tab.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateUsersInRange(string $minimum, ?string $maximum, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Same filters/bracket as {@see self::paginateUsersInRange()} but capped instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportUsersInRange(string $minimum, ?string $maximum, array $filters): array;
}
