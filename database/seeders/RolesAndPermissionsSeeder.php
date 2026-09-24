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
        $allPermissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', PermissionEnum::values())
            ->get();

        $superAdmin = Role::query()
            ->where('name', RoleEnum::SUPER_ADMIN->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        foreach ($allPermissions as $permission) {
            $superAdmin->givePermissionTo($permission);
        }

        $adminDenied = [
            PermissionEnum::ROLES_DELETE->value,
            PermissionEnum::ADMINS_DELETE->value,
        ];

        foreach ([RoleEnum::ADMIN] as $roleEnum) {
            $role = Role::query()
                ->where('name', $roleEnum->value)
                ->where('guard_name', self::GUARD)
                ->firstOrFail();

            foreach ($allPermissions as $permission) {
                if (in_array($permission->name, $adminDenied, true)) {
                    continue;
                }
                $role->givePermissionTo($permission);
            }
        }

        $this->grantInstitutionAdminPermissions($allPermissions);
    }

    /**
     * Only modules whose data is tenant-scoped are granted (fail closed), and never delete.
     * Left off: dashboard (national snapshot), custom fields, system configuration, roles.
     *
     * @param  \Illuminate\Support\Collection<int, Permission>  $allPermissions
     */
    private function grantInstitutionAdminPermissions($allPermissions): void
    {
        $role = Role::query()
            ->where('name', RoleEnum::INSTITUTION_ADMIN->value)
            ->where('guard_name', self::GUARD)
            ->firstOrFail();

        $granted = [
            PermissionEnum::CONSTITUENTS_CREATE->value,
            PermissionEnum::CONSTITUENTS_READ->value,
            PermissionEnum::CONSTITUENTS_UPDATE->value,
            PermissionEnum::DONATIONS_READ->value,
            PermissionEnum::CAMPAIGNS_READ->value,
            PermissionEnum::ADMINS_CREATE->value,
            PermissionEnum::ADMINS_READ->value,
            PermissionEnum::ADMINS_UPDATE->value,
            PermissionEnum::EVENTS_CREATE->value,
            PermissionEnum::EVENTS_READ->value,
            PermissionEnum::EVENTS_UPDATE->value,
            PermissionEnum::COMMUNICATIONS_CREATE->value,
            PermissionEnum::COMMUNICATIONS_READ->value,
            PermissionEnum::COMMUNICATIONS_UPDATE->value,
            PermissionEnum::MENTORSHIP_READ->value,
            PermissionEnum::MENTORSHIP_UPDATE->value,
            PermissionEnum::NETWORKING_CREATE->value,
            PermissionEnum::NETWORKING_READ->value,
            PermissionEnum::NETWORKING_UPDATE->value,
            PermissionEnum::CRM_CREATE->value,
            PermissionEnum::CRM_READ->value,
            PermissionEnum::CRM_UPDATE->value,
            PermissionEnum::REPORTS_CREATE->value,
            PermissionEnum::REPORTS_READ->value,
            PermissionEnum::AUDIT_TRAIL_READ->value,
        ];

        foreach ($allPermissions as $permission) {
            if (in_array($permission->name, $granted, true)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
