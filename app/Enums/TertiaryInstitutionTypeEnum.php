<?php

namespace App\Enums;

enum TertiaryInstitutionTypeEnum: string
{
    case UNIVERSITY = 'university';
    case POLYTECHNIC = 'polytechnic';
    case COLLEGE_OF_EDUCATION = 'college_of_education';
    case COLLEGE = 'college';
    case OTHER = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
