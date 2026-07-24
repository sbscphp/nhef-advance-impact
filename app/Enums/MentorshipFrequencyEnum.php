<?php

namespace App\Enums;

/** How often a mentor/mentee wants to interact. Shared by mentor and mentee profiles. */
enum MentorshipFrequencyEnum: string
{
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
