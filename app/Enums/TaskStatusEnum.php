<?php

namespace App\Enums;

enum TaskStatusEnum: string
{
    case PENDING = 'pending';
    case DONE = 'done';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
