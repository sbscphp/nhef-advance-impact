<?php

namespace App\Enums;

enum DegreeEnum: string
{
    case BSC = 'BSc';
    case BED = 'BEd';
    case BA = 'BA';
    case BENG = 'BEng';
    case LLB = 'LLB';
    case BTECH = 'BTech';
    case MSC = 'MSc';
    case MA = 'MA';
    case MBA = 'MBA';
    case MENG = 'MEng';
    case MPH = 'MPH';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
