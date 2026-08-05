<?php

namespace App\Services\ThirdParty\Payment;

use App\Enums\PaymentGatewayEnum;
use App\Exceptions\ApiException;

/**
 * Maps a gateway name to its {@see PaymentGatewayInterface} implementation. This is the one
 * place that knows every concrete gateway; everything else (PaymentGatewayService, the domain
 * services, the webhook controllers) only ever depends on the interface. Adding a gateway means
 * a new implementation class plus one line here, not touching any of those call sites.
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
