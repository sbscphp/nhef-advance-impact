<?php

namespace App\Enums;

/** How long a mentor commits to the programme. Mentor-only field. */
enum MentorshipCommitmentEnum: string
{
    case THREE_MONTHS = 'three_months';
    case SIX_MONTHS = 'six_months';
    case ONE_YEAR = 'one_year';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
