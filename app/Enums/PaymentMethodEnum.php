<?php

namespace App\Enums;

/** Payment options (BRD FEM-06): bank transfer, cards, digital wallet. */
enum PaymentMethodEnum: string
{
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case WALLET = 'wallet';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
