<?php

namespace App\Repositories\Contracts\Pledge;

use App\Models\Pledge;
use App\Models\PledgeInstallment;

interface PledgeInstallmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PledgeInstallment;

    public function firstByPledge(Pledge $pledge): ?PledgeInstallment;

    public function findByUuidForPledge(Pledge $pledge, string $uuid): ?PledgeInstallment;

    public function nextPending(Pledge $pledge): ?PledgeInstallment;

    public function markPaid(PledgeInstallment $installment): PledgeInstallment;
}
