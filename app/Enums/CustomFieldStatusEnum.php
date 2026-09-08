<?php

namespace App\Enums;

enum CustomFieldStatusEnum: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ARCHIVED => 'Archived',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
