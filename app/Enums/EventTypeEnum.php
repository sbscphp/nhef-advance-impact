<?php

namespace App\Enums;

enum EventTypeEnum: string
{
    case VIRTUAL = 'virtual';
    case PHYSICAL = 'physical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
