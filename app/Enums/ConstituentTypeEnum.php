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
            // AdvanceImpact calls this "Individual" (Nexus still says "Non-Alumni") - the case
            // name/value stay non_alumni either way, so existing stored data and code that checks
            // against the enum case/value are unaffected; only the display label differs.
            self::NON_ALUMNI => 'Individual',
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
