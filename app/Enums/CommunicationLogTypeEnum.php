<?php

namespace App\Enums;

enum CommunicationLogTypeEnum: string
{
    case PHONE = 'phone';
    case EMAIL = 'email';
    case VISIT = 'visit';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
