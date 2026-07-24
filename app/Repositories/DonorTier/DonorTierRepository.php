<?php

namespace App\Repositories\DonorTier;

use App\Models\DonorTier;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use Illuminate\Support\Collection;

class DonorTierRepository implements DonorTierRepositoryInterface
{
    public function allOrderedByThreshold(): Collection
    {
        return DonorTier::query()->orderBy('minimum_amount')->get();
    }

    public function findForAmount(string $amount): ?DonorTier
    {
        return DonorTier::query()
            ->where('minimum_amount', '<=', $amount)
            ->orderByDesc('minimum_amount')
            ->first();
    }
}
