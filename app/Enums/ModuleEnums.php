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

    public function label(): string
    {
        return match ($this) {
            self::guest => 'Guest',
            self::dashboard => 'Dashboard',
            self::alumni => 'Alumni',
            self::constituent_management => 'Constituent management',
            self::user_management => 'User management',
            self::audit_trail => 'Audit trail',
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
}
