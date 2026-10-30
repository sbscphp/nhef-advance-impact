<?php

namespace App\Enums;

enum ePermission: string
{
    case DASHBOARD_CREATE = 'dashboard.create';
    case DASHBOARD_READ = 'dashboard.read';
    case DASHBOARD_UPDATE = 'dashboard.update';
    case DASHBOARD_DELETE = 'dashboard.delete';

    case ALUMNI_CREATE = 'alumni.create';
    case ALUMNI_READ = 'alumni.read';
    case ALUMNI_UPDATE = 'alumni.update';
    case ALUMNI_DELETE = 'alumni.delete';

    case CONSTITUENTS_CREATE = 'constituents.create';
    case CONSTITUENTS_READ = 'constituents.read';
    case CONSTITUENTS_UPDATE = 'constituents.update';
    case CONSTITUENTS_DELETE = 'constituents.delete';

    case CAMPAIGNS_CREATE = 'campaigns.create';
    case CAMPAIGNS_READ = 'campaigns.read';
    case CAMPAIGNS_UPDATE = 'campaigns.update';
    case CAMPAIGNS_DELETE = 'campaigns.delete';

    // Finer-grained than campaigns.create: which campaign KIND an admin may create. Institution
    // Admin holds both; NHEF (Super Admin, Admin) holds only the National Giving Day one.
    case CAMPAIGNS_CREATE_STANDARD = 'campaigns.create_standard';
    case CAMPAIGNS_CREATE_NATIONAL_GIVING_DAY = 'campaigns.create_national_giving_day';

    // Announcing the yearly National Giving Day opening (the shared date) is a distinct, narrower
    // action from creating a campaign under it - Super Admin only, not even NHEF's plain "Admin".
    case CAMPAIGNS_ANNOUNCE_NATIONAL_GIVING_DAY = 'campaigns.announce_national_giving_day';

    case DONATIONS_CREATE = 'donations.create';
    case DONATIONS_READ = 'donations.read';
    case DONATIONS_UPDATE = 'donations.update';
    case DONATIONS_DELETE = 'donations.delete';

    // Bulk email, outbound calls.
    case COMMUNICATIONS_CREATE = 'communications.create';
    case COMMUNICATIONS_READ = 'communications.read';
    case COMMUNICATIONS_UPDATE = 'communications.update';
    case COMMUNICATIONS_DELETE = 'communications.delete';

    // Donor/partner recognition and relationship records.
    case CRM_CREATE = 'crm.create';
    case CRM_READ = 'crm.read';
    case CRM_UPDATE = 'crm.update';
    case CRM_DELETE = 'crm.delete';

    case EVENTS_CREATE = 'events.create';
    case EVENTS_READ = 'events.read';
    case EVENTS_UPDATE = 'events.update';
    case EVENTS_DELETE = 'events.delete';

    case REPORTS_CREATE = 'reports.create';
    case REPORTS_READ = 'reports.read';
    case REPORTS_UPDATE = 'reports.update';
    case REPORTS_DELETE = 'reports.delete';

    // Roles and admins share one section: both are "user management".
    case ROLES_CREATE = 'roles.create';
    case ROLES_READ = 'roles.read';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ADMINS_CREATE = 'admins.create';
    case ADMINS_READ = 'admins.read';
    case ADMINS_UPDATE = 'admins.update';
    case ADMINS_DELETE = 'admins.delete';

    case MENTORSHIP_CREATE = 'mentorship.create';
    case MENTORSHIP_READ = 'mentorship.read';
    case MENTORSHIP_UPDATE = 'mentorship.update';
    case MENTORSHIP_DELETE = 'mentorship.delete';

    case NETWORKING_CREATE = 'networking.create';
    case NETWORKING_READ = 'networking.read';
    case NETWORKING_UPDATE = 'networking.update';
    case NETWORKING_DELETE = 'networking.delete';

    case CUSTOM_FIELDS_CREATE = 'custom_fields.create';
    case CUSTOM_FIELDS_READ = 'custom_fields.read';
    case CUSTOM_FIELDS_UPDATE = 'custom_fields.update';
    case CUSTOM_FIELDS_DELETE = 'custom_fields.delete';

    // No longer gates anything: Donation Tier Configuration moved to its own donor_tiers.* below.
    // Left in place in case a role was given it directly via Roles & Permissions.
    case SYSTEM_CONFIGURATION_CREATE = 'system_configuration.create';
    case SYSTEM_CONFIGURATION_READ = 'system_configuration.read';
    case SYSTEM_CONFIGURATION_UPDATE = 'system_configuration.update';
    case SYSTEM_CONFIGURATION_DELETE = 'system_configuration.delete';

    // Constituency Type Configuration only, institution-admin-only. Kept separate from
    // system_configuration.* (a different audience) so a role can hold one without the other.
    case CONSTITUENCY_TYPES_CREATE = 'constituency_types.create';
    case CONSTITUENCY_TYPES_READ = 'constituency_types.read';
    case CONSTITUENCY_TYPES_UPDATE = 'constituency_types.update';
    case CONSTITUENCY_TYPES_DELETE = 'constituency_types.delete';

    // Institution-scoped; NHEF and each institution's own admin can both hold this, with
    // DonorTierService enforcing per-row ownership internally rather than a permission per scope.
    case DONOR_TIERS_CREATE = 'donor_tiers.create';
    case DONOR_TIERS_READ = 'donor_tiers.read';
    case DONOR_TIERS_UPDATE = 'donor_tiers.update';
    case DONOR_TIERS_DELETE = 'donor_tiers.delete';

    // Read-only; there is no create/update/delete for the audit trail itself.
    case AUDIT_TRAIL_READ = 'audit_trail.read';

    // Read-only, same as audit_trail.read - the notification list/summary/detail views, not the
    // self-service actions on your own notifications (mark read/unread, dismiss, read-all), which
    // stay ungated for every admin regardless of role.
    case NOTIFICATIONS_READ = 'notifications.read';

    // Advance-Impact-only: projects, milestones, deliverables, budgets, expenditures, impact reports, documents.
    case PROJECTS_CREATE = 'projects.create';
    case PROJECTS_READ = 'projects.read';
    case PROJECTS_UPDATE = 'projects.update';
    case PROJECTS_DELETE = 'projects.delete';

    // Advance-Impact-only: research initiatives, objectives, milestones, deliverables.
    case RESEARCH_CREATE = 'research.create';
    case RESEARCH_READ = 'research.read';
    case RESEARCH_UPDATE = 'research.update';
    case RESEARCH_DELETE = 'research.delete';

    // Data-visibility toggles rather than CRUD: institution-level money, and individual donor/alumni records.
    case VISIBILITY_MONETARY = 'visibility.monetary';
    case VISIBILITY_INDIVIDUAL_RECORDS = 'visibility.individual_records';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
