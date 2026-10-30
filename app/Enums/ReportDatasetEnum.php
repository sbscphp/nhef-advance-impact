<?php

namespace App\Enums;

use App\Helpers\PermissionModuleMapper;
use App\Models\Admin;
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
    case MENTORSHIP = 'mentorship';
    case MENTOR = 'mentor';
    case MENTEE = 'mentee';
    case NETWORKING = 'networking';
    case ADMIN_USER = 'admin_user';
    case INSTITUTION = 'institution';
    case AUDIT_TRAIL = 'audit_trail';

    public function label(): string
    {
        return match ($this) {
            self::ALUMNI => 'Alumni',
            self::DONATION => 'Donations',
            self::CAMPAIGN => 'Fundraising Campaigns',
            self::EVENT => 'Events',
            self::PLEDGE => 'Pledges',
            self::PROSPECT => 'CRM Prospects',
            self::MENTORSHIP => 'Mentorship',
            self::MENTOR => 'Mentors',
            self::MENTEE => 'Mentees',
            self::NETWORKING => 'Networking',
            self::ADMIN_USER => 'Admin Users',
            self::INSTITUTION => 'Institutions',
            self::AUDIT_TRAIL => 'Audit Trail',
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
            self::MENTORSHIP => 'Mentor/mentee matches and their review outcomes.',
            self::MENTOR => 'Mentor directory: profiles, capacity, and their current mentees.',
            self::MENTEE => 'Mentee directory: profiles and their matched mentor.',
            self::NETWORKING => 'Alumni networking channels and their activity.',
            self::ADMIN_USER => 'Admin/staff accounts and their access.',
            self::INSTITUTION => 'Partner universities and their aggregated metrics.',
            self::AUDIT_TRAIL => 'System and user activity across every module, same as the live Audit Trail list.',
        };
    }

    /**
     * The custom-field applicable-module this dataset merges "Display in reports" fields from;
     * null for datasets with no corresponding custom-field module.
     */
    public function customFieldModule(): ?string
    {
        return match ($this) {
            self::ALUMNI => ModuleEnums::constituent_management->value,
            self::DONATION, self::PLEDGE => ModuleEnums::donation->value,
            self::EVENT => ModuleEnums::events->value,
            self::PROSPECT => ModuleEnums::crm->value,
            self::ADMIN_USER => ModuleEnums::user_management->value,
            self::CAMPAIGN, self::MENTORSHIP, self::MENTOR, self::MENTEE, self::NETWORKING, self::INSTITUTION, self::AUDIT_TRAIL => null,
        };
    }

    /**
     * The ModuleEnums key this dataset lives under, for permission-scoping reports (unlike
     * {@see self::customFieldModule()}, which only answers the custom-field question).
     */
    public function module(): string
    {
        return match ($this) {
            // Institution Admin holds alumni.read but NOT constituents.read, so ALUMNI must not
            // share Institution's module key below.
            self::ALUMNI => ModuleEnums::alumni->value,
            self::INSTITUTION => ModuleEnums::constituent_management->value,
            self::DONATION, self::PLEDGE => ModuleEnums::donation->value,
            self::CAMPAIGN => ModuleEnums::fundraising->value,
            self::EVENT => ModuleEnums::events->value,
            self::PROSPECT => ModuleEnums::crm->value,
            self::MENTORSHIP, self::MENTOR, self::MENTEE => ModuleEnums::mentorship->value,
            self::NETWORKING => ModuleEnums::networking->value,
            self::ADMIN_USER => ModuleEnums::user_management->value,
            self::AUDIT_TRAIL => ModuleEnums::audit_trail->value,
        };
    }

    /** Institution admins only ever see their own institution, so this dataset is NHEF-only. */
    private function landlordOnly(): bool
    {
        return $this === self::INSTITUTION;
    }

    /**
     * Rows are individual people, so viewers limited to summaries cannot use them. AUDIT_TRAIL is
     * excluded: AuditTrailReportDataset filters its individual-level rows out itself instead.
     */
    private function individualLevel(): bool
    {
        return in_array($this, [
            self::ALUMNI,
            self::DONATION,
            self::PLEDGE,
            self::EVENT,
            self::PROSPECT,
            self::MENTORSHIP,
            self::MENTOR,
            self::MENTEE,
        ], true);
    }

    public function availableToViewer(): bool
    {
        if ($this->landlordOnly() && Institution::checkCurrent()) {
            return false;
        }

        if ($this->individualLevel() && ! ViewerVisibility::canSeeIndividualRecords()) {
            return false;
        }

        return self::viewerHasModuleAccess($this->module());
    }

    /** Same module-access gate as Notifications/Audit Trail; non-admins are unrestricted, same as {@see ViewerVisibility}. */
    private static function viewerHasModuleAccess(string $module): bool
    {
        $actor = request()->user();

        if (! $actor instanceof Admin) {
            return true;
        }

        $restricted = PermissionModuleMapper::restrictedModuleKeys(
            $actor->getAllPermissions()->pluck('name')->all()
        );

        return ! in_array($module, $restricted, true);
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
