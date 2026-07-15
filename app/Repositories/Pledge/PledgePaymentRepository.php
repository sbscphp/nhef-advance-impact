<?php

namespace App\Repositories\Pledge;

use App\Enums\PaymentStatusEnum;
use App\Models\PledgePayment;
use App\Repositories\Contracts\Pledge\PledgePaymentRepositoryInterface;

class PledgePaymentRepository implements PledgePaymentRepositoryInterface
{
    public function create(array $data): PledgePayment
    {
        return PledgePayment::create($data);
    }

    public function findByReference(string $reference): ?PledgePayment
    {
        return PledgePayment::query()
            ->with(['pledge.campaign', 'installment', 'user'])
            ->where('gateway_reference', $reference)
            ->first();
    }

    public function markFailed(PledgePayment $payment): PledgePayment
    {
        $payment->forceFill(['status' => PaymentStatusEnum::FAILED->value])->save();

        return $payment;
    }

    public function markSuccessful(PledgePayment $payment, array $data): PledgePayment
    {
        $payment->forceFill(array_merge(['status' => PaymentStatusEnum::SUCCESSFUL->value], $data))->save();

        return $payment;
    }
}
