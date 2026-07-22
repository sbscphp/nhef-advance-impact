<?php

namespace App\Enums;

enum EventStatusEnum: string
{
    /** Not yet visible to donors/guests. */
    case DRAFT = 'draft';
    /** Visible and open for ticket registration (subject to its own date/capacity limits). */
    case PUBLISHED = 'published';
    /** Called off; no further registrations accepted. */
    case CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
