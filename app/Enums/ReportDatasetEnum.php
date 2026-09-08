<?php

namespace App\Enums;

enum ReportDatasetEnum: string
{
    case ALUMNI = 'alumni';
    case DONATION = 'donation';
    case CAMPAIGN = 'campaign';
    case EVENT = 'event';
    case PLEDGE = 'pledge';
    case PROSPECT = 'prospect';
    case MAIL = 'mail';
    case MENTORSHIP = 'mentorship';
    case NETWORKING = 'networking';
    case ADMIN_USER = 'admin_user';
    case EVENT_WAITLIST = 'event_waitlist';

    public function label(): string
    {
        return match ($this) {
            self::ALUMNI => 'Alumni',
            self::DONATION => 'Donations',
            self::CAMPAIGN => 'Campaign',
            self::EVENT => 'Event',
            self::PLEDGE => 'Pledges',
            self::PROSPECT => 'CRM Prospects',
            self::MAIL => 'Mail Campaigns',
            self::MENTORSHIP => 'Mentorship',
            self::NETWORKING => 'Networking',
            self::ADMIN_USER => 'Admin Users',
            self::EVENT_WAITLIST => 'Event Waitlist',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ALUMNI => 'Graduate and former students across partnering institutions.',
            self::DONATION => 'Every contribution received across campaigns and channels.',
            self::CAMPAIGN => 'Fundraising campaigns and their performance metrics.',
            self::EVENT => 'Event registrations across the system.',
            self::PLEDGE => 'Pledged commitments and their installment progress.',
            self::PROSPECT => 'Donor pipeline leads tracked by the CRM.',
            self::MAIL => 'Bulk email campaigns sent to constituents.',
            self::MENTORSHIP => 'Mentor/mentee matches and their review outcomes.',
            self::NETWORKING => 'Alumni networking channels and their activity.',
            self::ADMIN_USER => 'Admin/staff accounts and their access.',
            self::EVENT_WAITLIST => 'Attendees waitlisted once an event reached capacity.',
        };
    }

    /**
     * The custom-field applicable-module this dataset merges "Display in reports" fields from;
     * null for datasets with no corresponding custom-field module, or where no value could ever
     * be attached to this specific record type (Event Waitlist).
     */
    public function customFieldModule(): ?string
    {
        return match ($this) {
            self::ALUMNI => ModuleEnums::constituent_management->value,
            self::DONATION, self::PLEDGE => ModuleEnums::donation->value,
            self::EVENT => ModuleEnums::events->value,
            self::PROSPECT => ModuleEnums::crm->value,
            self::MAIL => ModuleEnums::communications->value,
            self::ADMIN_USER => ModuleEnums::user_management->value,
            self::CAMPAIGN, self::MENTORSHIP, self::NETWORKING, self::EVENT_WAITLIST => null,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
