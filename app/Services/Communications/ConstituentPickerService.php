<?php

namespace App\Services\Communications;

use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/** Backs the "Select a donor" constituent picker shared by the mail composer and call-log form. */
class ConstituentPickerService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->userRepository->paginateForSegment($filters, $perPage);
    }
}
