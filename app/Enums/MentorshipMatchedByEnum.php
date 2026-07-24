<?php

namespace App\Enums;

/** How a match was made. Only `system` is produced today; `admin` is reserved for a future manual-match feature. */
enum MentorshipMatchedByEnum: string
{
    case SYSTEM = 'system';
    case ADMIN = 'admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
