<?php

namespace App\Enums;

enum ConstituentTypeEnum: string
{
    case ALUMNI = 'alumni';
    case NON_ALUMNI = 'non_alumni';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::ALUMNI => 'Alumni',
            self::NON_ALUMNI => 'Non-Alumni',
            self::ORGANIZATION => 'Organisation',
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
