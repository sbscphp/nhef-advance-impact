<?php

namespace App\Enums;

enum ePermission: string
{
    // Dashboard
    case DASHBOARD_CREATE = 'dashboard.create';
    case DASHBOARD_READ = 'dashboard.read';
    case DASHBOARD_UPDATE = 'dashboard.update';
    case DASHBOARD_DELETE = 'dashboard.delete';

    // Alumni
    case ALUMNI_CREATE = 'alumni.create';
    case ALUMNI_READ = 'alumni.read';
    case ALUMNI_UPDATE = 'alumni.update';
    case ALUMNI_DELETE = 'alumni.delete';

    // Constituent management
    case CONSTITUENTS_CREATE = 'constituents.create';
    case CONSTITUENTS_READ = 'constituents.read';
    case CONSTITUENTS_UPDATE = 'constituents.update';
    case CONSTITUENTS_DELETE = 'constituents.delete';

    // Fundraising campaigns
    case CAMPAIGNS_CREATE = 'campaigns.create';
    case CAMPAIGNS_READ = 'campaigns.read';
    case CAMPAIGNS_UPDATE = 'campaigns.update';
    case CAMPAIGNS_DELETE = 'campaigns.delete';

    // Donations
    case DONATIONS_CREATE = 'donations.create';
    case DONATIONS_READ = 'donations.read';
    case DONATIONS_UPDATE = 'donations.update';
    case DONATIONS_DELETE = 'donations.delete';

    // Communications (bulk email, outbound calls)
    case COMMUNICATIONS_CREATE = 'communications.create';
    case COMMUNICATIONS_READ = 'communications.read';
    case COMMUNICATIONS_UPDATE = 'communications.update';
    case COMMUNICATIONS_DELETE = 'communications.delete';

    // CRM (donor/partner recognition and relationship records)
    case CRM_CREATE = 'crm.create';
    case CRM_READ = 'crm.read';
    case CRM_UPDATE = 'crm.update';
    case CRM_DELETE = 'crm.delete';

    // Events
    case EVENTS_CREATE = 'events.create';
    case EVENTS_READ = 'events.read';
    case EVENTS_UPDATE = 'events.update';
    case EVENTS_DELETE = 'events.delete';

    // Reporting
    case REPORTS_CREATE = 'reports.create';
    case REPORTS_READ = 'reports.read';
    case REPORTS_UPDATE = 'reports.update';
    case REPORTS_DELETE = 'reports.delete';

    // User management (roles + admins)
    case ROLES_CREATE = 'roles.create';
    case ROLES_READ = 'roles.read';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ADMINS_CREATE = 'admins.create';
    case ADMINS_READ = 'admins.read';
    case ADMINS_UPDATE = 'admins.update';
    case ADMINS_DELETE = 'admins.delete';

    // Mentorship
    case MENTORSHIP_CREATE = 'mentorship.create';
    case MENTORSHIP_READ = 'mentorship.read';
    case MENTORSHIP_UPDATE = 'mentorship.update';
    case MENTORSHIP_DELETE = 'mentorship.delete';

    // Networking
    case NETWORKING_CREATE = 'networking.create';
    case NETWORKING_READ = 'networking.read';
    case NETWORKING_UPDATE = 'networking.update';
    case NETWORKING_DELETE = 'networking.delete';

    // Custom fields
    case CUSTOM_FIELDS_CREATE = 'custom_fields.create';
    case CUSTOM_FIELDS_READ = 'custom_fields.read';
    case CUSTOM_FIELDS_UPDATE = 'custom_fields.update';
    case CUSTOM_FIELDS_DELETE = 'custom_fields.delete';

    // System configuration
    case SYSTEM_CONFIGURATION_CREATE = 'system_configuration.create';
    case SYSTEM_CONFIGURATION_READ = 'system_configuration.read';
    case SYSTEM_CONFIGURATION_UPDATE = 'system_configuration.update';
    case SYSTEM_CONFIGURATION_DELETE = 'system_configuration.delete';

    // Audit trail (read API)
    case AUDIT_TRAIL_READ = 'audit_trail.read';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
