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

    public function markFailed(EventRegistrationPayment $payment): EventRegistrationPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(EventRegistrationPayment $payment, array $data): EventRegistrationPayment;
}
