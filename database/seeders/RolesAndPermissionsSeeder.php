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

        // NHEF-level accounts match exactly what their own Figma menu shows; Alumni, Networking,
        // Mentorship, Custom Field, Communications, CRM and standalone Events are Institution
        // Admin's own menu items, not NHEF's, so they're excluded below (can't be toggled back
        // on via Roles & Permissions) even though NHEF previously held them all via the old
        // "everything except X" blanket grant. events.read and alumni.read are carved back out
        // below for Super Admin only (Event Tracking widget and the institution detail page's
        // Alumni tab respectively); create/update/delete on both stay excluded.
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

        // The Donation module itself stays off for both NHEF roles. Super Admin's
        // individual-record visibility is unrelated and now carved back out separately below, on
        // every module whose base *.read they already hold.
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

        // One-time corrections: `grant()`/givePermissionTo() only adds, never strips, so
        // permissions removed from the lists above/below have to be explicitly revoked from
        // whatever a prior seeder run already granted in the DB.
        // - system_configuration.* was briefly granted to Institution Admin for Constituency
        //   Type Configuration, which now has its own constituency_types.* set; revoked.
        // - NHEF previously held every permission just excluded above via the old blanket
        //   grant, before alumni/networking/mentorship/etc. were added to the exclusion list.
        $institutionAdminRole = Role::query()
            ->where('name', RoleEnum::INSTITUTION_ADMIN->value)
            ->where('guard_name', self::GUARD)
            ->first();
        $institutionAdminRole?->revokePermissionTo([
            PermissionEnum::SYSTEM_CONFIGURATION_CREATE->value,
            PermissionEnum::SYSTEM_CONFIGURATION_READ->value,
            PermissionEnum::SYSTEM_CONFIGURATION_UPDATE->value,
            PermissionEnum::SYSTEM_CONFIGURATION_DELETE->value,
            // Institution Admin's own alumni.* grant below replaces this; a `landlord`
            // middleware already blocks them from the constituents/institutions routes anyway.
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
        // roles.* IS granted: an institution admin may manage custom roles, scoped by
        // RoleService to rows where `institution_id` matches their own (the shared "Institution
        // Admin" template stays visible but Super-Admin-only to edit); a custom role's
        // permissions are also capped to a subset of the creator's own.
        // dashboard.read IS granted; the NHEF-only cards branch on AdminScopeEnum internally
        // (DashboardService), so this only unlocks the institution-scoped shape.
        // campaigns.create IS granted (institutions create their own scoped campaigns); adding an
        // institution to an *existing* campaign still asserts AdminScopeEnum::NHEF internally.
        // campaigns.create_standard/create_national_giving_day are granted too: which campaign
        // KIND an admin may create is permission-based (checked in CampaignService), not scope-based.
        // constituency_types.* and alumni.* (replacing constituents.*) are this institution's own
        // individual-constituent modules, granted the same way.
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

        // Read-only evaluator. Reports.create is only the export/download action. Also sees
        // visibility.monetary, audit_trail.read, alumni.read, visibility.individual_records,
        // events.read and mentorship.read: later Figma screens show named individual rows and
        // these modules directly, reversing earlier "FME never sees X" decisions.
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
