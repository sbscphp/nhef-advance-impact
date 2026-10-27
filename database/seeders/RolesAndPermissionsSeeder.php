<?php

namespace Database\Seeders;

use App\Enums\ePermission as PermissionEnum;
use App\Enums\eRole as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    // php artisan db:seed --class=RolesAndPermissionsSeeder

    use WithoutModelEvents;

    private const GUARD = 'api';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->syncPermissionsFromEnum();
        $this->removeObsoletePermissions();
        $this->upsertAllowableRoles();
        $this->grantDefaultRolePermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncPermissionsFromEnum(): void
    {
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate([
                'name' => $perm->value,
                'guard_name' => self::GUARD,
            ]);
        }
    }

    /**
     * Remove permission rows not defined in ePermission (e.g. legacy names).
     */
    private function removeObsoletePermissions(): void
    {
        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', PermissionEnum::values())
            ->delete();
    }

    private function upsertAllowableRoles(): void
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(
                [
                    'name' => $roleEnum->value,
                    'guard_name' => self::GUARD,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                ]
            );

            if ($role->uuid === null || $role->uuid === '') {
                $role->forceFill(['uuid' => (string) Str::uuid()])->save();
            }
        }
    }

    /**
     * Idempotent: only adds missing permissions (givePermissionTo).
     * Does not strip custom grants on seeded roles.
     */
    private function grantDefaultRolePermissions(): void
    {
        $everything = PermissionEnum::cases();

        // AdvanceImpact Super Admin/Admin accounts (2026-10-26 cleanup): grant matches exactly
        // what their own AdvanceImpact Figma sidebar shows - Dashboard, Constituent Management,
        // User Management, Reporting, Audit Trail, Notification, System Configuration, My Profile
        // Setting - nothing else. Alumni, Networking, Mentorship, Custom Field, Communications,
        // CRM, Events, Fundraising/Campaigns, Projects and Research are all institution-level,
        // either Institution Admin's own menu items or opt-in modules toggled per institution via
        // the Invite Institution "System Access Allowable" checklist - never Super Admin/Admin's
        // own CRUD, so none of them belong in this blanket "everything except X" grant and none
        // can be silently toggled back on via Roles & Permissions. constituency_types.* is
        // excluded for the same reason (see its own grant below).
        $nhefExcluded = [
            PermissionEnum::CONSTITUENCY_TYPES_CREATE,
            PermissionEnum::CONSTITUENCY_TYPES_READ,
            PermissionEnum::CONSTITUENCY_TYPES_UPDATE,
            PermissionEnum::CONSTITUENCY_TYPES_DELETE,
            PermissionEnum::ALUMNI_CREATE,
            PermissionEnum::ALUMNI_READ,
            PermissionEnum::ALUMNI_UPDATE,
            PermissionEnum::ALUMNI_DELETE,
            PermissionEnum::NETWORKING_CREATE,
            PermissionEnum::NETWORKING_READ,
            PermissionEnum::NETWORKING_UPDATE,
            PermissionEnum::NETWORKING_DELETE,
            PermissionEnum::MENTORSHIP_CREATE,
            PermissionEnum::MENTORSHIP_READ,
            PermissionEnum::MENTORSHIP_UPDATE,
            PermissionEnum::MENTORSHIP_DELETE,
            PermissionEnum::CUSTOM_FIELDS_CREATE,
            PermissionEnum::CUSTOM_FIELDS_READ,
            PermissionEnum::CUSTOM_FIELDS_UPDATE,
            PermissionEnum::CUSTOM_FIELDS_DELETE,
            PermissionEnum::COMMUNICATIONS_CREATE,
            PermissionEnum::COMMUNICATIONS_READ,
            PermissionEnum::COMMUNICATIONS_UPDATE,
            PermissionEnum::COMMUNICATIONS_DELETE,
            PermissionEnum::CRM_CREATE,
            PermissionEnum::CRM_READ,
            PermissionEnum::CRM_UPDATE,
            PermissionEnum::CRM_DELETE,
            PermissionEnum::EVENTS_CREATE,
            PermissionEnum::EVENTS_READ,
            PermissionEnum::EVENTS_UPDATE,
            PermissionEnum::EVENTS_DELETE,
            PermissionEnum::CAMPAIGNS_CREATE,
            PermissionEnum::CAMPAIGNS_READ,
            PermissionEnum::CAMPAIGNS_UPDATE,
            PermissionEnum::CAMPAIGNS_DELETE,
            PermissionEnum::CAMPAIGNS_CREATE_STANDARD,
            PermissionEnum::CAMPAIGNS_CREATE_NATIONAL_GIVING_DAY,
            PermissionEnum::CAMPAIGNS_ANNOUNCE_NATIONAL_GIVING_DAY,
            PermissionEnum::PROJECTS_CREATE,
            PermissionEnum::PROJECTS_READ,
            PermissionEnum::PROJECTS_UPDATE,
            PermissionEnum::PROJECTS_DELETE,
            PermissionEnum::RESEARCH_CREATE,
            PermissionEnum::RESEARCH_READ,
            PermissionEnum::RESEARCH_UPDATE,
            PermissionEnum::RESEARCH_DELETE,
        ];

        // Super Admin-only, not shared with Admin: the Donation module itself stays off for both
        // NHEF roles (2026-10-02: Super Admin no longer needs donations.* at all, including the
        // aggregate-only /donation/overview it was originally kept for) - that decision is
        // unrelated to individual-record visibility, which Super Admin now holds too (2026-10-20:
        // previously revoked below along with donations.*, now carved back out so Super Admin can
        // see individual-level records on every module whose base *.read permission they already
        // hold - Constituents, System Configuration; still has no effect on Donations since Super
        // Admin lacks *.read on it).
        $this->grant(RoleEnum::SUPER_ADMIN, $this->except($everything, [
            ...$nhefExcluded,
            PermissionEnum::DONATIONS_CREATE,
            PermissionEnum::DONATIONS_READ,
            PermissionEnum::DONATIONS_UPDATE,
            PermissionEnum::DONATIONS_DELETE,
        ]));

        $this->grant(RoleEnum::ADMIN, $this->except($everything, [
            ...$nhefExcluded,
            PermissionEnum::ROLES_DELETE,
            PermissionEnum::ADMINS_DELETE,
        ]));

        // One-time corrections (2026-09-30): `grant()`/givePermissionTo() only adds, never strips,
        // so permissions removed from the lists above/below have to be explicitly revoked from
        // whatever a prior seeder run already granted in the DB.
        // - system_configuration.* was briefly granted to Institution Admin so they could reach
        //   Constituency Type Configuration, which shared that permission bucket with Donation
        //   Tier Configuration (NHEF-only) - Constituency Type Configuration now has its own
        //   constituency_types.* permission set below, this old grant is revoked.
        // - NHEF (Super Admin, Admin) previously held every permission just excluded above via the
        //   old "everything except X" list, before alumni/networking/mentorship/custom_fields/
        //   communications/crm/events were added to the exclusion list - revoked here too.
        $institutionAdminRole = Role::query()
            ->where('name', RoleEnum::INSTITUTION_ADMIN->value)
            ->where('guard_name', self::GUARD)
            ->first();
        $institutionAdminRole?->revokePermissionTo([
            PermissionEnum::SYSTEM_CONFIGURATION_CREATE->value,
            PermissionEnum::SYSTEM_CONFIGURATION_READ->value,
            PermissionEnum::SYSTEM_CONFIGURATION_UPDATE->value,
            PermissionEnum::SYSTEM_CONFIGURATION_DELETE->value,
            // Institution Admin's own alumni.* grant below replaces this; the
            // constituents/institutions routes also carry a `landlord` middleware that already
            // blocks Institution Admin outright, so constituents.* was never doing anything for
            // them besides unlocking their own alumni list.
            PermissionEnum::CONSTITUENTS_CREATE->value,
            PermissionEnum::CONSTITUENTS_READ->value,
            PermissionEnum::CONSTITUENTS_UPDATE->value,
        ]);

        foreach ([RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN] as $nhefRole) {
            Role::query()
                ->where('name', $nhefRole->value)
                ->where('guard_name', self::GUARD)
                ->first()
                ?->revokePermissionTo(array_map(fn (PermissionEnum $permission): string => $permission->value, $nhefExcluded));
        }

        // Donations only (see the grant above) - Admin keeps the Donation module. Individual-
        // record visibility is no longer revoked here (2026-10-20) - see that grant's comment.
        Role::query()
            ->where('name', RoleEnum::SUPER_ADMIN->value)
            ->where('guard_name', self::GUARD)
            ->first()
            ?->revokePermissionTo([
                PermissionEnum::DONATIONS_CREATE->value,
                PermissionEnum::DONATIONS_READ->value,
                PermissionEnum::DONATIONS_UPDATE->value,
                PermissionEnum::DONATIONS_DELETE->value,
            ]);

        // Fail closed: only tenant-scoped modules, never delete. Left off: custom fields.
        // roles.* IS now granted (2026-10-16): an institution admin may create/manage custom
        // roles for their own team. `RoleService` scopes this to rows where `institution_id`
        // matches the actor's own institution - the shared "Institution Admin" template
        // (institution_id null) stays visible for reference but is never editable/deletable by
        // them, Super-Admin-only, same as every other global/system role. A custom role's
        // permissions are also capped to a subset of the creating admin's own, enforced in
        // RoleService::create()/update(), so nobody can hand out a permission they don't hold.
        // dashboard.read IS granted, but the NHEF-only dashboard cards (National Snapshot,
        // Active Campaign Tracking) branch on AdminScopeEnum internally (DashboardService), so this
        // only unlocks the institution-scoped shape of the shared endpoints, never the NHEF-wide
        // one. Likewise campaigns.create IS granted (BSA: institutions can create their own scoped
        // campaigns), and CampaignService::addInstitution() still asserts AdminScopeEnum::NHEF
        // internally (adding an institution to an existing campaign is an NHEF-only action,
        // unrelated to creation); createNationalGivingDay() forces the institutions/assigned-
        // officer/bank-account payload to all belong to the caller's own institution, so this never
        // unlocks another institution's campaign. campaigns.create_standard and
        // campaigns.create_national_giving_day are both granted too (2026-09-30): which campaign
        // KIND an admin may create is permission-based, checked inside
        // CampaignService::create()/createNationalGivingDay() instead of scope logic, so the
        // frontend can read these two directly. constituency_types.* (its own permission set, NOT
        // system_configuration.*, see the correction above) is granted too, checked inside
        // ConstituencyTypeService the same way. alumni.* (replacing constituents.*, see the
        // correction above) is this institution's own individual-constituent module.
        $this->grant(RoleEnum::INSTITUTION_ADMIN, [
            PermissionEnum::DASHBOARD_READ,
            PermissionEnum::ALUMNI_CREATE,
            PermissionEnum::ALUMNI_READ,
            PermissionEnum::ALUMNI_UPDATE,
            PermissionEnum::DONATIONS_READ,
            PermissionEnum::CAMPAIGNS_CREATE,
            PermissionEnum::CAMPAIGNS_CREATE_STANDARD,
            PermissionEnum::CAMPAIGNS_CREATE_NATIONAL_GIVING_DAY,
            PermissionEnum::CAMPAIGNS_READ,
            PermissionEnum::ADMINS_CREATE,
            PermissionEnum::ADMINS_READ,
            PermissionEnum::ADMINS_UPDATE,
            PermissionEnum::ROLES_CREATE,
            PermissionEnum::ROLES_READ,
            PermissionEnum::ROLES_UPDATE,
            PermissionEnum::ROLES_DELETE,
            PermissionEnum::EVENTS_CREATE,
            PermissionEnum::EVENTS_READ,
            PermissionEnum::EVENTS_UPDATE,
            PermissionEnum::COMMUNICATIONS_CREATE,
            PermissionEnum::COMMUNICATIONS_READ,
            PermissionEnum::COMMUNICATIONS_UPDATE,
            PermissionEnum::MENTORSHIP_READ,
            PermissionEnum::MENTORSHIP_UPDATE,
            PermissionEnum::NETWORKING_CREATE,
            PermissionEnum::NETWORKING_READ,
            PermissionEnum::NETWORKING_UPDATE,
            PermissionEnum::CRM_CREATE,
            PermissionEnum::CRM_READ,
            PermissionEnum::CRM_UPDATE,
            // AdvanceImpact opt-in modules (2026-10-27): held at the role level like CRM/Campaigns
            // above, but whether they're actually usable on a given institution is additionally
            // gated per-institution by EnsureInstitutionModuleGranted against the "System Access
            // Allowable" grant Super Admin set at invite time - granting the role here is not the
            // same as granting the module.
            PermissionEnum::PROJECTS_CREATE,
            PermissionEnum::PROJECTS_READ,
            PermissionEnum::PROJECTS_UPDATE,
            PermissionEnum::PROJECTS_DELETE,
            PermissionEnum::RESEARCH_CREATE,
            PermissionEnum::RESEARCH_READ,
            PermissionEnum::RESEARCH_UPDATE,
            PermissionEnum::RESEARCH_DELETE,
            PermissionEnum::CUSTOM_FIELDS_CREATE,
            PermissionEnum::CUSTOM_FIELDS_READ,
            PermissionEnum::CUSTOM_FIELDS_UPDATE,
            PermissionEnum::CUSTOM_FIELDS_DELETE,
            PermissionEnum::REPORTS_CREATE,
            PermissionEnum::REPORTS_READ,
            PermissionEnum::AUDIT_TRAIL_READ,
            PermissionEnum::NOTIFICATIONS_READ,
            PermissionEnum::CONSTITUENCY_TYPES_CREATE,
            PermissionEnum::CONSTITUENCY_TYPES_READ,
            PermissionEnum::CONSTITUENCY_TYPES_UPDATE,
            PermissionEnum::CONSTITUENCY_TYPES_DELETE,
            // Institution-scoped (2026-10-22): manages only this institution's own donor tiers,
            // enforced inside DonorTierService, not by a narrower permission - same split as
            // roles.* above for institution-scoped custom roles.
            PermissionEnum::DONOR_TIERS_CREATE,
            PermissionEnum::DONOR_TIERS_READ,
            PermissionEnum::DONOR_TIERS_UPDATE,
            PermissionEnum::DONOR_TIERS_DELETE,
            PermissionEnum::VISIBILITY_MONETARY,
            PermissionEnum::VISIBILITY_INDIVIDUAL_RECORDS,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function grant(RoleEnum $roleEnum, array $permissions): void
    {
        $role = Role::query()
            ->where('name', $roleEnum->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $role->givePermissionTo(array_map(fn (PermissionEnum $permission): string => $permission->value, $permissions));
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     * @param  list<PermissionEnum>  $excluded
     * @return list<PermissionEnum>
     */
    private function except(array $permissions, array $excluded): array
    {
        return array_values(array_filter($permissions, fn (PermissionEnum $permission): bool => ! in_array($permission, $excluded, true)));
    }
}
