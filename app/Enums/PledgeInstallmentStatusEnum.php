<?php

namespace App\Enums;

enum PledgeInstallmentStatusEnum: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case MISSED = 'missed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
