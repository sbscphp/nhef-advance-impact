<?php

namespace App\Enums;

enum EmploymentStatusEnum: string
{
    case EMPLOYED = 'Employed';
    case UNEMPLOYED = 'Unemployed';
    case SELF_EMPLOYED = 'Self Employed';
    case RETIRED = 'Retired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
