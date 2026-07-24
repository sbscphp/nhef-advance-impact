<?php

namespace App\Enums;

enum PledgeStatusEnum: string
{
    case PENDING = 'pending';
    case ON_TRACK = 'on_track';
    case DUE = 'due';
    case COMPLETED = 'completed';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
