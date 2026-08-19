<?php

namespace App\Enums;

/**
 * Promotion status shown on the admin Waitlist tab. Only PENDING is ever set by code today;
 * ACCEPTED/DECLINED/PAID are reserved for a future promotion workflow (BRD EVT-01 waitlist).
 */
enum EventWaitlistStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case PAID = 'paid';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
