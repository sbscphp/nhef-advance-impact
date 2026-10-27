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
     * Roles a valid institution-scoped admin account may legitimately hold. This is a "does the
     * role fit this account type" check (used by Admin::assertRolesFitScope() to validate any
     * admin's role set, including the one AdminRepository::createInstitutionOwner() assigns when
     * an institution is first onboarded) - it is NOT "who may grant this role to someone", which
     * is a separate, narrower question handled explicitly in AdminUserService::assertRoleAssignable()
     * and RoleService::reassignUsers() (2026-10-16): an institution's own admin can never GRANT
     * "Institution Admin" to anyone else, even though the role itself still fits their account
     * type - only a Super Admin may hand it out. An institution instead creates scoped custom
     * roles for its team - see RoleService::create()'s institution_id handling.
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
