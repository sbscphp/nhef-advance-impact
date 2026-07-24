<?php

namespace App\Repositories\Pledge;

use App\Enums\PledgeInstallmentStatusEnum;
use App\Models\Pledge;
use App\Models\PledgeInstallment;
use App\Repositories\Contracts\Pledge\PledgeInstallmentRepositoryInterface;

class PledgeInstallmentRepository implements PledgeInstallmentRepositoryInterface
{
    public function create(array $data): PledgeInstallment
    {
        return PledgeInstallment::create($data);
    }

    public function firstByPledge(Pledge $pledge): ?PledgeInstallment
    {
        return $pledge->installments()->orderBy('sequence')->first();
    }

    public function findByUuidForPledge(Pledge $pledge, string $uuid): ?PledgeInstallment
    {
        return $pledge->installments()->where('uuid', $uuid)->first();
    }

    public function nextPending(Pledge $pledge): ?PledgeInstallment
    {
        return $pledge->installments()
            ->where('status', PledgeInstallmentStatusEnum::PENDING->value)
            ->orderBy('sequence')
            ->first();
    }

    public function markPaid(PledgeInstallment $installment): PledgeInstallment
    {
        $installment->forceFill(['status' => PledgeInstallmentStatusEnum::PAID->value, 'paid_at' => now()])->save();

        return $installment;
    }
}
