<?php

namespace App\Repositories\Contracts\Pledge;

use App\Models\PledgePayment;

interface PledgePaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PledgePayment;

    public function findByReference(string $reference): ?PledgePayment;

    public function markFailed(PledgePayment $payment): PledgePayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(PledgePayment $payment, array $data): PledgePayment;
}
