<?php

namespace App\Enums;

enum ProjectBroadcastDeliveryEnum: string
{
    case EMAIL = 'email';
    case PUSH_NOTIFICATION = 'push_notification';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PUSH_NOTIFICATION => 'Push Notification',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
