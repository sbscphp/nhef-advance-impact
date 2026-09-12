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
    case PROJECT = 'project';
    case PROJECT_MILESTONE = 'project_milestone';
    case PROJECT_DELIVERABLE = 'project_deliverable';
    case PROJECT_BUDGET_LINE = 'project_budget_line';
    case PROJECT_EXPENDITURE = 'project_expenditure';
    case PROJECT_IMPACT_REPORT = 'project_impact_report';
    case PROJECT_RISK = 'project_risk';
    case PROJECT_BROADCAST = 'project_broadcast';

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
            self::PROJECT => 'Project',
            self::PROJECT_MILESTONE => 'Project Milestone',
            self::PROJECT_DELIVERABLE => 'Project Deliverable',
            self::PROJECT_BUDGET_LINE => 'Project Budget Line',
            self::PROJECT_EXPENDITURE => 'Project Expenditure',
            self::PROJECT_IMPACT_REPORT => 'Project Impact Report',
            self::PROJECT_RISK => 'Project Risk',
            self::PROJECT_BROADCAST => 'Project Broadcast',
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
            self::PROJECT => 'Projects tracked under Project Management.',
            self::PROJECT_MILESTONE => 'Milestones raised against tracked projects.',
            self::PROJECT_DELIVERABLE => 'Deliverables raised against tracked projects.',
            self::PROJECT_BUDGET_LINE => 'Budget lines allocated within tracked projects.',
            self::PROJECT_EXPENDITURE => 'Expenditures recorded against project budget lines.',
            self::PROJECT_IMPACT_REPORT => 'Impact reports submitted for tracked projects.',
            self::PROJECT_RISK => 'Risks raised against tracked projects.',
            self::PROJECT_BROADCAST => 'Broadcasts sent to project teams.',
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
            self::CAMPAIGN, self::MENTORSHIP, self::NETWORKING, self::EVENT_WAITLIST,
            self::PROJECT, self::PROJECT_MILESTONE, self::PROJECT_DELIVERABLE, self::PROJECT_BUDGET_LINE,
            self::PROJECT_EXPENDITURE, self::PROJECT_IMPACT_REPORT, self::PROJECT_RISK, self::PROJECT_BROADCAST => null,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
