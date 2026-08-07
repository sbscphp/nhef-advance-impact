<?php

namespace App\Services\ThirdParty\Payment;

use App\Enums\PaymentGatewayEnum;
use App\Exceptions\ApiException;

/**
 * Maps a gateway name to its {@see PaymentGatewayInterface} implementation; the one place
 * that knows every concrete gateway, so adding one means a new class plus one line here.
 */
class PaymentGatewayResolver
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly StripeService $stripeService,
    ) {}

    public function make(?string $gateway = null): PaymentGatewayInterface
    {
        return match ($gateway ?? $this->default()) {
            PaymentGatewayEnum::PAYSTACK->value => $this->paystackService,
            PaymentGatewayEnum::STRIPE->value => $this->stripeService,
            default => throw new ApiException("Unsupported payment gateway: {$gateway}", 500),
        };
    }

    /** The gateway new payments are initialized with, selected via PAYMENT_GATEWAY. */
    public function default(): string
    {
        $gateway = strtolower(trim((string) config('services.payment.default')));

        return in_array($gateway, PaymentGatewayEnum::values(), true) ? $gateway : PaymentGatewayEnum::STRIPE->value;
    }
}
