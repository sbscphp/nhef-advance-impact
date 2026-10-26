<?php

namespace App\Enums;

/** The "Type of Sponsorship" dropdown shown when a project's "Is this a sponsored Project" toggle is on. */
enum ProjectSponsorshipTypeEnum: string
{
    case SPONSORSHIP_GRANT = 'sponsorship_grant';
    case EDUCATIONAL_SCHOLARSHIP_FUND = 'educational_scholarship_fund';
    case COMMUNITY_OUTREACH_PROGRAM = 'community_outreach_program';
    case HEALTHCARE_SUPPORT_PLAN = 'healthcare_support_plan';
    case ENVIRONMENTAL_INITIATIVE = 'environmental_initiative';

    public function label(): string
    {
        return match ($this) {
            self::SPONSORSHIP_GRANT => 'Sponsorship Grant',
            self::EDUCATIONAL_SCHOLARSHIP_FUND => 'Educational Scholarship Fund',
            self::COMMUNITY_OUTREACH_PROGRAM => 'Community Outreach Program',
            self::HEALTHCARE_SUPPORT_PLAN => 'Healthcare Support Plan',
            self::ENVIRONMENTAL_INITIATIVE => 'Environmental Initiative',
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
