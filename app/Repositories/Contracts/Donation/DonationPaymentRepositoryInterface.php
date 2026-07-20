<?php

namespace App\Repositories\Contracts\Donation;

use App\Models\DonationPayment;

interface DonationPaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DonationPayment;

    public function findByReference(string $reference): ?DonationPayment;

    public function markFailed(DonationPayment $payment): DonationPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(DonationPayment $payment, array $data): DonationPayment;
}
