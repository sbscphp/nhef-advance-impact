<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\Prospect;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProspectRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Prospect;

    public function findByUuid(string $uuid): ?Prospect;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Prospect $prospect, array $data): Prospect;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * All prospects grouped by pipeline stage, for the Kanban board.
     *
     * @return Collection<string, Collection<int, Prospect>>
     */
    public function kanban(): Collection;
}
