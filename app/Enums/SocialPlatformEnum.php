<?php

namespace App\Enums;

/** Platform options for a mentor/mentee's "Socials" entries. Shared by mentor and mentee profiles. */
enum SocialPlatformEnum: string
{
    case INSTAGRAM = 'instagram';
    case TWITTER = 'twitter';
    case LINKEDIN = 'linkedin';
    case FACEBOOK = 'facebook';
    case TIKTOK = 'tiktok';
    case OTHERS = 'others';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
