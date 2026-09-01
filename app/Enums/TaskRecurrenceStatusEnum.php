<?php

namespace App\Enums;

enum TaskRecurrenceStatusEnum: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case DISABLED = 'disabled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
