<?php

namespace App\Repositories\Event;

use App\Enums\PaymentStatusEnum;
use App\Models\EventRegistrationPayment;
use App\Repositories\Contracts\Event\EventRegistrationPaymentRepositoryInterface;

class EventRegistrationPaymentRepository implements EventRegistrationPaymentRepositoryInterface
{
    public function create(array $data): EventRegistrationPayment
    {
        return EventRegistrationPayment::create($data);
    }

    public function findByReference(string $reference): ?EventRegistrationPayment
    {
        return EventRegistrationPayment::query()
            ->with(['registration.event', 'registration.items.ticketType', 'user'])
            ->where('gateway_reference', $reference)
            ->first();
    }

    public function markFailed(EventRegistrationPayment $payment): EventRegistrationPayment
    {
        $payment->forceFill(['status' => PaymentStatusEnum::FAILED->value])->save();

        return $payment;
    }

    public function markSuccessful(EventRegistrationPayment $payment, array $data): EventRegistrationPayment
    {
        $payment->forceFill(array_merge(['status' => PaymentStatusEnum::SUCCESSFUL->value], $data))->save();

        return $payment;
    }
}
