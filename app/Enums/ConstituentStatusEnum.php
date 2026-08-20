<?php

namespace App\Enums;

enum ConstituentStatusEnum: string
{
    case INVITE_SENT = 'invite_sent';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case ACCESS_REVOKED = 'access_revoked';
    case COMPLETED = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
