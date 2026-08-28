<?php

namespace App\Enums;

enum ProspectInviteTypeEnum: string
{
    case ONLINE = 'online';
    case PHYSICAL = 'physical';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
