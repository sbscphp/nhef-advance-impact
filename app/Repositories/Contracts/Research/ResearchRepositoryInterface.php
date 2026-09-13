<?php

namespace App\Repositories\Contracts\Research;

use App\Models\Research;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface ResearchRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Research;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Research;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Research $research, array $data): Research;

    /**
     * @return array{all: int, open: int, completed: int}
     */
    public function countByStatus(?CarbonInterface $start = null, ?CarbonInterface $end = null): array;
}
