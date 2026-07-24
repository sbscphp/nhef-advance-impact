<?php

namespace App\Enums;

enum EventRegistrationStatusEnum: string
{
    /** Created; payment not yet confirmed (skipped entirely for free tickets). */
    case PENDING = 'pending';
    /** Payment succeeded (or the ticket was free), seats/quantities counted. */
    case COMPLETED = 'completed';
    /** Payment failed or was not completed. */
    case FAILED = 'failed';
    /** Cancelled before payment completed. */
    case CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
