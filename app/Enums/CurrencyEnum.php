<?php

namespace App\Enums;

/**
 * Supported donation currencies (BRD FEM-01). Live gateway dispatch currently targets
 * Paystack, which settles NGN and USD; GBP/EUR pledges are accepted but will need a
 * different gateway wired into {@see \App\Services\ThirdParty\Payment\PaymentGatewayInterface}
 * before `live` mode can process them.
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
