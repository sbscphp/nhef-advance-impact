<?php

namespace App\Enums;

/** Staff vetting status of a mentor application (independent of {@see MentorListingStatusEnum}). */
enum MentorReviewStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
