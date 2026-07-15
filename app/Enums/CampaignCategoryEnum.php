<?php

namespace App\Enums;

/**
 * Donation purpose selection (BRD FEM-02): scholarship, research, and endowment funds, etc.
 */
enum CampaignCategoryEnum: string
{
    case SCHOLARSHIP = 'scholarship';
    case RESEARCH = 'research';
    case ENDOWMENT = 'endowment';
    case INFRASTRUCTURE = 'infrastructure';
    case COMMUNITY = 'community';
    case GENERAL = 'general';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SCHOLARSHIP => 'Scholarship Fund',
            self::RESEARCH => 'Research Fund',
            self::ENDOWMENT => 'Endowment Fund',
            self::INFRASTRUCTURE => 'Infrastructure',
            self::COMMUNITY => 'Community Outreach',
            self::GENERAL => 'General Giving',
        };
    }
}
