<?php

namespace App\Enums;

use App\Models\Institution;
use App\Support\ViewerVisibility;

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
    case INSTITUTION = 'institution';

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
            self::INSTITUTION => 'Institution',
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
            self::INSTITUTION => 'Partner universities and their aggregated metrics.',
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
            self::CAMPAIGN, self::MENTORSHIP, self::NETWORKING, self::EVENT_WAITLIST, self::INSTITUTION => null,
        };
    }

    /** Institution admins only ever see their own institution, so this dataset is NHEF-only. */
    private function landlordOnly(): bool
    {
        return $this === self::INSTITUTION;
    }

    /** Rows are individual people (alumni, donors, attendees, mentors), so viewers limited to summaries cannot use them. */
    private function individualLevel(): bool
    {
        return in_array($this, [
            self::ALUMNI,
            self::DONATION,
            self::PLEDGE,
            self::EVENT,
            self::EVENT_WAITLIST,
            self::PROSPECT,
            self::MENTORSHIP,
        ], true);
    }

    public function availableToViewer(): bool
    {
        if ($this->landlordOnly() && Institution::checkCurrent()) {
            return false;
        }

        return ! $this->individualLevel() || ViewerVisibility::canSeeIndividualRecords();
    }

    /**
     * Dataset keys the current viewer cannot use, for filtering saved-report history.
     *
     * @return list<string>
     */
    public static function hiddenFromViewer(): array
    {
        return array_values(array_map(
            fn (self $dataset): string => $dataset->value,
            array_filter(self::cases(), fn (self $dataset): bool => ! $dataset->availableToViewer()),
        ));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
