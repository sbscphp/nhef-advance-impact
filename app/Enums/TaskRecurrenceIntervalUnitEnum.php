<?php

namespace App\Enums;

enum TaskRecurrenceIntervalUnitEnum: string
{
    case HOUR = 'hour';
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
