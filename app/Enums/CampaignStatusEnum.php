<?php

namespace App\Enums;

enum CampaignStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case DEACTIVATED = 'deactivated';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Statuses a customer is allowed to browse and donate to. */
    public static function publiclyVisible(): array
    {
        return [self::ACTIVE->value];
    }
}
