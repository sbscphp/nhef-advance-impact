<?php

namespace App\Repositories\Contracts\Campaign;

use App\Models\NationalGivingDayOpening;
use Illuminate\Pagination\LengthAwarePaginator;

interface NationalGivingDayOpeningRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): NationalGivingDayOpening;

    public function findByUuid(string $uuid): ?NationalGivingDayOpening;

    public function slugExists(string $slug): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;
}
