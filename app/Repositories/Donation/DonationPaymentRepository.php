<?php

namespace App\Repositories\Donation;

use App\Enums\PaymentStatusEnum;
use App\Models\DonationPayment;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;

class DonationPaymentRepository implements DonationPaymentRepositoryInterface
{
    public function create(array $data): DonationPayment
    {
        return DonationPayment::create($data);
    }

    public function findByReference(string $reference): ?DonationPayment
    {
        return DonationPayment::query()
            ->with(['donation.campaign', 'user'])
            ->where('gateway_reference', $reference)
            ->first();
    }

    public function markFailed(DonationPayment $payment): DonationPayment
    {
        $payment->forceFill(['status' => PaymentStatusEnum::FAILED->value])->save();

        return $payment;
    }

    public function markSuccessful(DonationPayment $payment, array $data): DonationPayment
    {
        $payment->forceFill(array_merge(['status' => PaymentStatusEnum::SUCCESSFUL->value], $data))->save();

        return $payment;
    }
}
