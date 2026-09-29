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

        // NHEF-level accounts see institution aggregates, never individual donor/alumni records.
        $this->grant(RoleEnum::SUPER_ADMIN, $this->except($everything, [PermissionEnum::VISIBILITY_INDIVIDUAL_RECORDS]));

        $this->grant(RoleEnum::ADMIN, $this->except($everything, [
            PermissionEnum::ROLES_DELETE,
            PermissionEnum::ADMINS_DELETE,
        ]));

        // Fail closed: only tenant-scoped modules, never delete. Left off: custom fields, roles.
        // dashboard.read IS granted, but the NHEF-only dashboard actions (national snapshot,
        // campaign/event tracking, institution ranking) each additionally assert
        // AdminScopeEnum::NHEF internally, so this only unlocks the institution-scoped
        // dashboard/institution/* endpoints, not the NHEF-wide ones. Likewise campaigns.create IS
        // granted (BSA: institutions can create their own scoped National Giving Day campaign),
        // but CampaignService::create()/addInstitution() each assert AdminScopeEnum::NHEF
        // internally, and createNationalGivingDay() forces the institutions/assigned-officer/
        // bank-account payload to all belong to the caller's own institution, so this never
        // unlocks a standard campaign or another institution's campaign. system_configuration.* IS
        // now granted too (Constituency Type management is institution-admin-only), but
        // DonorTierService asserts AdminScopeEnum::NHEF internally on every action, so this never
        // unlocks Donation Tier Configuration for an institution.
        $this->grant(RoleEnum::INSTITUTION_ADMIN, [
            PermissionEnum::DASHBOARD_READ,
            PermissionEnum::CONSTITUENTS_CREATE,
            PermissionEnum::CONSTITUENTS_READ,
            PermissionEnum::CONSTITUENTS_UPDATE,
            PermissionEnum::DONATIONS_READ,
            PermissionEnum::CAMPAIGNS_CREATE,
            PermissionEnum::CAMPAIGNS_READ,
            PermissionEnum::ADMINS_CREATE,
            PermissionEnum::ADMINS_READ,
            PermissionEnum::ADMINS_UPDATE,
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
            PermissionEnum::SYSTEM_CONFIGURATION_CREATE,
            PermissionEnum::SYSTEM_CONFIGURATION_READ,
            PermissionEnum::SYSTEM_CONFIGURATION_UPDATE,
            PermissionEnum::SYSTEM_CONFIGURATION_DELETE,
            PermissionEnum::VISIBILITY_MONETARY,
            PermissionEnum::VISIBILITY_INDIVIDUAL_RECORDS,
        ]);

        // Read-only evaluator: institution-level summaries, no money, no individual records, no audit trail.
        // Reports.create is only the export/download action.
        $this->grant(RoleEnum::MINISTRY_OF_EDUCATION, [
            PermissionEnum::DASHBOARD_READ,
            PermissionEnum::CONSTITUENTS_READ,
            PermissionEnum::CAMPAIGNS_READ,
            PermissionEnum::EVENTS_READ,
            PermissionEnum::REPORTS_READ,
            PermissionEnum::REPORTS_CREATE,
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
