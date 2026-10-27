<?php

namespace App\Enums;

/**
 * Canonical admin module keys for permissions UI and audit logging ({@see PermissionModuleMapper}).
 */
enum ModuleEnums: string
{
    /** Default when audit context has no module. */
    case guest = 'guest';

    case dashboard = 'dashboard';
    case alumni = 'alumni';
    case constituent_management = 'constituent_management';
    case user_management = 'user_management';
    case audit_trail = 'audit_trail';
    case notification = 'notification';

    /** Personal account settings (profile, password, 2FA). */
    case settings = 'settings';

    /** Admin/customer sign-in and session events (not tied to CRUD permissions map). */
    case authentication = 'authentication';

    /** Campaigns, pledges, donations, and payment gateway events. */
    case fundraising = 'fundraising';

    /** Donation records and receipts, as a permission-matrix module distinct from campaign management. */
    case donation = 'donation';

    case communications = 'communications';

    /** Donor/partner recognition and relationship records. */
    case crm = 'crm';

    /** Events, ticket types, and ticket registration/payment events. */
    case events = 'events';

    case reporting = 'reporting';

    /** Mentor/mentee applications, matches, and reviews. */
    case mentorship = 'mentorship';

    case networking = 'networking';
    case custom_field = 'custom_field';
    case system_configuration = 'system_configuration';

    /** Advance-Impact-only: projects, milestones, deliverables, budgets, and impact reports. */
    case project_management = 'project_management';

    /** Advance-Impact-only: research initiatives, objectives, milestones, and deliverables. */
    case research_management = 'research_management';

    /** Advance-Impact-only: not built yet, reserved as a System Access Allowable toggle. */
    case proposal = 'proposal';

    /** Constituency Type Configuration; kept separate from system_configuration (a different audience). */
    case constituency_type = 'constituency_type';

    /** Donation Tier Configuration; institution-scoped, moved off system_configuration (2026-10-22). */
    case donor_tier = 'donor_tier';

    public function label(): string
    {
        return match ($this) {
            self::guest => 'Guest',
            self::dashboard => 'Dashboard',
            self::alumni => 'Alumni',
            self::constituent_management => 'Constituent management',
            self::user_management => 'User management',
            self::audit_trail => 'Audit trail',
            self::notification => 'Notification',
            self::settings => 'Settings',
            self::authentication => 'Authentication',
            self::fundraising => 'Fundraising',
            self::donation => 'Donation',
            self::communications => 'Communications',
            self::crm => 'CRM',
            self::events => 'Events',
            self::reporting => 'Reporting',
            self::mentorship => 'Mentorship',
            self::networking => 'Networking',
            self::custom_field => 'Custom field',
            self::system_configuration => 'System configuration',
            self::project_management => 'Project management',
            self::research_management => 'Research management',
            self::proposal => 'Proposal',
            self::constituency_type => 'Constituency type',
            self::donor_tier => 'Donation tier configuration',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Modules a Custom Field definition may target; excludes unbuilt/nonexistent ones like
     * `reporting` and the Advance-Impact-only Project/Proposal/Research group.
     *
     * @return list<string>
     */
    public static function customFieldModules(): array
    {
        return [
            self::crm->value,
            self::constituent_management->value,
            self::communications->value,
            self::donation->value,
            self::events->value,
            self::user_management->value,
        ];
    }

    /**
     * Subset of {@see self::customFieldModules()} a customer/guest may use; `crm` and
     * `user_management` records aren't customer-owned, and `communications` has no
     * customer-facing form to fill in.
     *
     * @return list<string>
     */
    public static function customerCustomFieldModules(): array
    {
        return [
            self::constituent_management->value,
            self::donation->value,
            self::events->value,
        ];
    }

    /**
     * The AdvanceImpact Invite Institution screen's "System Access Allowable" checklist - which
     * modules Super Admin may grant an institution. Excludes `settings` (always-on/personal, not
     * a toggle) and every Nexus-only module (`alumni`, `communications`, `events`, `mentorship`,
     * `networking`, `constituency_type`, `donor_tier`), `guest`, and `authentication`.
     *
     * @return list<string>
     */
    public static function institutionModuleKeys(): array
    {
        return array_map(fn (self $module): string => $module->value, [
            self::dashboard,
            self::crm,
            self::constituent_management,
            self::fundraising,
            self::project_management,
            self::proposal,
            self::research_management,
            self::user_management,
            self::donation,
            self::reporting,
            self::custom_field,
            self::audit_trail,
            self::notification,
            self::system_configuration,
        ]);
    }

    /**
     * Subset of {@see self::institutionModuleKeys()} actually enforced against
     * `institution_modules` ({@see \App\Http\Middleware\EnsureInstitutionModuleGranted}) - the
     * structural basics (dashboard, constituent_management, user_management, reporting,
     * audit_trail, notification, system_configuration) stay on regardless of the checklist, since
     * an institution needs them to administer itself at all and an accidental unchecked box must
     * never lock an institution out of its own account. Matches the 2026-10-09 client minutes:
     * constituent/database management is core, Research/Proposal/Project Management are opt-in -
     * extended here to the other Nexus-parity modules (CRM, Fundraising, Donation, Custom Fields)
     * for the same "institution picks what it needs" reasoning.
     *
     * @return list<string>
     */
    public static function institutionGateableModuleKeys(): array
    {
        return array_map(fn (self $module): string => $module->value, [
            self::crm,
            self::fundraising,
            self::donation,
            self::project_management,
            self::proposal,
            self::research_management,
            self::custom_field,
        ]);
    }

    /**
     * Modules whose events and notifications name individual donors, alumni or attendees.
     *
     * @return list<string>
     */
    public static function individualLevelValues(): array
    {
        return array_map(fn (self $module): string => $module->value, [
            self::alumni,
            self::constituent_management,
            self::fundraising,
            self::donation,
            self::communications,
            self::crm,
            self::events,
            self::mentorship,
            self::networking,
        ]);
    }
}
