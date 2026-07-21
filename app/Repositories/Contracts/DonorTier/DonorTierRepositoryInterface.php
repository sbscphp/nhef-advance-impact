<?php

namespace App\Repositories\Contracts\DonorTier;

use App\Models\DonorTier;
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
}
