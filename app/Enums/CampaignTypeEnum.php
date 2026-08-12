<?php

namespace App\Enums;

enum CampaignTypeEnum: string
{
    case STANDARD = 'standard';
    case NATIONAL_GIVING_DAY = 'national_giving_day';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
