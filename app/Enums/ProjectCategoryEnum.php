<?php

namespace App\Enums;

enum ProjectCategoryEnum: string
{
    case RESEARCH = 'research';
    case EDUCATION = 'education';
    case INFRASTRUCTURE = 'infrastructure';
    case COMMUNITY_DEVELOPMENT = 'community_development';
    case SCHOLARSHIP = 'scholarship';
    case HEALTHCARE = 'healthcare';

    public function label(): string
    {
        return match ($this) {
            self::RESEARCH => 'Research',
            self::EDUCATION => 'Education',
            self::INFRASTRUCTURE => 'Infrastructure',
            self::COMMUNITY_DEVELOPMENT => 'Community Development',
            self::SCHOLARSHIP => 'Scholarship',
            self::HEALTHCARE => 'Healthcare',
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
