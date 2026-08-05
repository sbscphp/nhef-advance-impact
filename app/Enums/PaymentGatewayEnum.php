<?php

namespace App\Enums;

use App\Services\ThirdParty\Payment\PaymentGatewayInterface;

/** Hosted-checkout gateways behind {@see PaymentGatewayInterface}. */
enum PaymentGatewayEnum: string
{
    case PAYSTACK = 'paystack';
    case STRIPE = 'stripe';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PAYSTACK => 'Paystack',
            self::STRIPE => 'Stripe',
        };
    }
}
