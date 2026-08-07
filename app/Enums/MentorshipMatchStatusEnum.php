<?php

namespace App\Enums;

/**
 * Every match is created ACTIVE (see MentorMatchingService::match) and reviews can only be
 * submitted against one; COMPLETED/CANCELLED are reserved for a future "end mentorship" feature.
 */
enum MentorshipMatchStatusEnum: string
{
    /** Mentor and mentee are currently paired; the mentorship is ongoing. */
    case ACTIVE = 'active';

    /** The mentorship ran its course and ended normally (e.g. reached the end of the commitment period). */
    case COMPLETED = 'completed';

    /** The mentorship ended early, before reaching a natural conclusion (e.g. one side dropped out). */
    case CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
