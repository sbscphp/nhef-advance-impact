<?php

namespace App\Repositories\Bank;

use App\Models\Bank;
use App\Repositories\Contracts\Bank\BankRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BankRepository implements BankRepositoryInterface
{
    public function listActive(): Collection
    {
        return Bank::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function findByUuid(string $uuid): ?Bank
    {
        return Bank::query()->where('uuid', $uuid)->first();
    }
}
