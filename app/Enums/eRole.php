<?php

namespace App\Enums;

enum eRole: string
{
    case ADMIN = 'Admin';
    case CUSTOMER = 'Customer';
    case SUPER_ADMIN = 'Super Admin';
    case INSTITUTION_ADMIN = 'Institution Admin';

    /**
     * Role names synced by the roles/permissions database seeder.
     *
     * @return list<string>
     */
    public static function allowed(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles a valid institution-scoped admin account may hold (used by Admin::assertRolesFitScope()).
     * This is "does the role fit the account type", not "who may grant it": an institution's own
     * admin can never grant "Institution Admin" itself, only a Super Admin can; see
     * AdminUserService::assertRoleAssignable(). Institutions instead create their own scoped
     * custom roles via RoleService::create().
     *
     * @return list<string>
     */
    public static function institutionAssignable(): array
    {
        return [self::INSTITUTION_ADMIN->value];
    }

    /**
     * Roles only a Super Admin may hand out: they carry NHEF-wide reach.
     *
     * @return list<string>
     */
    public static function superAdminAssignable(): array
    {
        return [self::SUPER_ADMIN->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
