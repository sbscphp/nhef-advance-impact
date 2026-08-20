<?php

namespace App\Repositories\Contracts\Event;

use App\Models\EventRegistrationPayment;

interface EventRegistrationPaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EventRegistrationPayment;

    public function findByReference(string $reference): ?EventRegistrationPayment;

    /**
     * Same as {@see self::findByReference()} but with `lockForUpdate()`; call only inside an
     * open transaction, right before settling, to close the race between the webhook and the
     * frontend-driven verify call both reaching the "not yet successful" check at once.
     */
    public function findByReferenceForUpdate(string $reference): ?EventRegistrationPayment;

    public function markFailed(EventRegistrationPayment $payment): EventRegistrationPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(EventRegistrationPayment $payment, array $data): EventRegistrationPayment;
}
