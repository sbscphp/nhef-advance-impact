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

    /**
     * Same as {@see self::findByReference()} but with `lockForUpdate()`; call only inside an
     * open transaction, right before settling, to close the race between the webhook and the
     * frontend-driven verify call both reaching the "not yet successful" check at once.
     */
    public function findByReferenceForUpdate(string $reference): ?PledgePayment;

    public function markFailed(PledgePayment $payment): PledgePayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(PledgePayment $payment, array $data): PledgePayment;
}
