<?php

namespace App\Repositories\Contracts\Institution;

use App\Models\Institution;
use Illuminate\Support\Collection;

interface InstitutionRepositoryInterface
{
    /**
     * @return Collection<int, Institution>
     */
    public function all(bool $activeOnly): Collection;

    public function findByUuid(string $uuid): ?Institution;

    public function nameExists(string $name): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Institution;
}
