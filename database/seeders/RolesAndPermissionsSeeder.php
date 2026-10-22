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

        // NHEF-level accounts see institution aggregates, never individual donor/alumni records,
        // and their permission grant matches exactly what their own Figma menu shows (2026-09-30
        // pass): Dashboard, Institution Management, National Giving Day, Report, User Management,
        // Audit Trail, Notification, System Configuration, My Profile Setting. Alumni, Networking,
        // Mentorship, Custom Field, Communications, CRM and (standalone) Events are Institution
        // Admin's own menu items, not NHEF's, even though NHEF previously held all of them via this
        // "everything except X" blanket grant - excluded below so the frontend's permission-driven
        // menus only show what NHEF can actually use, and none of these can be silently toggled
        // back on for NHEF via Roles & Permissions. campaigns.create_standard and
        // constituency_types.* are excluded for the same reason (see their own grants below).
        // dashboard.read alone (not events.read) is what gates the Dashboard's "Event Tracking"
        // widget, so excluding events.* here never touched that widget either way.
        // events.read is carved back out below (2026-10-20) for Super Admin only, so they can view
        // the standalone Events module read-only; events.create/update/delete and Admin's own
        // events.read stay excluded. alumni.read is carved back out the same way (2026-10-21):
        // the "Partner Institutions" > institution detail page's Alumni tab
        // (GET .../institutions/{uuid}/alumni) needs it, and 403'd for Super Admin without it -
        // alumni.create/update/delete and Admin's own alumni.read stay excluded.
        $nhefExcluded = [
            PermissionEnum::CAMPAIGNS_CREATE_STANDARD,
            PermissionEnum::CONSTITUENCY_TYPES_CREATE,
            PermissionEnum::CONSTITUENCY_TYPES_READ,
            PermissionEnum::CONSTITUENCY_TYPES_UPDATE,
            PermissionEnum::CONSTITUENCY_TYPES_DELETE,
            PermissionEnum::ALUMNI_CREATE,
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
            PermissionEnum::EVENTS_UPDATE,
            PermissionEnum::EVENTS_DELETE,
        ];

        // Super Admin-only, not shared with Admin: the Donation module itself stays off for both
        // NHEF roles (2026-10-02: Super Admin no longer needs donations.* at all, including the
        // aggregate-only /donation/overview it was originally kept for) - that decision is
        // unrelated to individual-record visibility, which Super Admin now holds too (2026-10-20:
        // previously revoked below along with donations.*, now carved back out so Super Admin can
        // see individual-level records on every module whose base *.read permission they already
        // hold - Constituents, Campaigns, Events, System Configuration; still has no effect on
        // Donations, Alumni, Communications since Super Admin lacks *.read on those).
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
            // Announcing the National Giving Day opening is Super Admin only (2026-10-20), not
            // shared with NHEF's plain "Admin" role - narrower than the existing
            // campaigns.create_national_giving_day, which both already hold.
            PermissionEnum::CAMPAIGNS_ANNOUNCE_NATIONAL_GIVING_DAY,
            // Super Admin only (2026-10-20, see the grant above) - Admin keeps the rest of
            // events.* excluded via $nhefExcluded.
            PermissionEnum::EVENTS_READ,
            // Super Admin only (2026-10-21, see the grant above) - Admin keeps the rest of
            // alumni.* excluded via $nhefExcluded.
            PermissionEnum::ALUMNI_READ,
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
            PermissionEnum::REPORTS_CREATE,
            PermissionEnum::REPORTS_READ,
            PermissionEnum::AUDIT_TRAIL_READ,
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

        // Read-only evaluator. Reports.create is only the export/download action. 2026-10-14 FME
        // dashboard review: this role now sees institution-level money (visibility.monetary) and
        // the Live Activity feed (audit_trail.read). 2026-10-14 FME Constituents/Alumni/Partner
        // Institutions screens: this role now also sees named individual constituent and alumni
        // records (alumni.read, visibility.individual_records) - reverses the 22 Sep decision that
        // FME never sees individual records under any circumstances; the new Figma screens show
        // named rows directly, so per the client's own screens that rule no longer holds.
        // 2026-10-14 FME Events/Mentorship screens: this role now also sees events.read and
        // mentorship.read - reverses the 2026-10-02 "no Event Management screen for this role,
        // same as Super Admin" decision, same reasoning (new screens win).
        $this->grant(RoleEnum::MINISTRY_OF_EDUCATION, [
            PermissionEnum::DASHBOARD_READ,
            PermissionEnum::CONSTITUENTS_READ,
            PermissionEnum::ALUMNI_READ,
            PermissionEnum::CAMPAIGNS_READ,
            PermissionEnum::EVENTS_READ,
            PermissionEnum::MENTORSHIP_READ,
            PermissionEnum::REPORTS_READ,
            PermissionEnum::REPORTS_CREATE,
            PermissionEnum::AUDIT_TRAIL_READ,
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
