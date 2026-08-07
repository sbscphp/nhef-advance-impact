<?php

namespace App\Enums;

use App\Services\ThirdParty\Payment\PaymentGatewayInterface;

/**
 * Supported donation currencies (BRD FEM-01). Paystack (the current gateway) settles NGN
 * and USD; GBP/EUR pledges are accepted but need a different gateway wired into
 * {@see PaymentGatewayInterface} before `live` mode can process them.
 */
enum CurrencyEnum: string
{
    case NGN = 'NGN';
    case USD = 'USD';
    case GBP = 'GBP';
    case EUR = 'EUR';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
