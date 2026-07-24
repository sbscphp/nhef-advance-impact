<?php

namespace App\Enums;

enum PledgeFrequencyEnum: string
{
    case ONE_TIME = 'one_time';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUALLY = 'annually';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isRecurring(): bool
    {
        return $this !== self::ONE_TIME;
    }

    /** Calendar months to add between installments for this frequency. */
    public function intervalMonths(): int
    {
        return match ($this) {
            self::ONE_TIME => 0,
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::ANNUALLY => 12,
        };
    }
}
