<?php

namespace App\Repositories\Contracts\Bank;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Collection;

interface BankRepositoryInterface
{
    /**
     * @return Collection<int, Bank>
     */
    public function listActive(): Collection;

    public function findByUuid(string $uuid): ?Bank;
}
