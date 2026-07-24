<?php

namespace App\Enums;

enum DonationStatusEnum: string
{
    /** Created; first payment not yet confirmed. */
    case PENDING = 'pending';

    /** Recurring donation with at least one successful charge, awaiting its next cycle. */
    case ACTIVE = 'active';

    /** Recurring donation temporarily paused by the donor; no further cycles charged until resumed. */
    case PAUSED = 'paused';

    /** One-time donation whose single payment succeeded. */
    case COMPLETED = 'completed';

    /** Initial payment failed or was not completed. */
    case FAILED = 'failed';

    /** Recurring donation cancelled before a further cycle was charged. */
    case CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
