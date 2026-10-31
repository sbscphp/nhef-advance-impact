<?php

namespace App\Enums;

/** Payment options (BRD FEM-06): online gateway methods plus the manual-entry options an admin can record a gift under. */
enum PaymentMethodEnum: string
{
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case WIRE_TRANSFER = 'wire_transfer';
    case CASH = 'cash';
    case CHECK = 'check';
    case MOBILE_WALLET = 'mobile_wallet';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::WIRE_TRANSFER => 'Wire Transfer',
            self::CASH => 'Cash',
            self::CHECK => 'Check',
            self::MOBILE_WALLET => 'Mobile Wallet',
        };
    }
}
