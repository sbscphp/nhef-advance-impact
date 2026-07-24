<?php

namespace App\Enums;

/** A mentor's own visibility toggle for receiving new matches, separate from staff review. */
enum MentorListingStatusEnum: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
