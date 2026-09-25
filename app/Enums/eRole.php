<?php

namespace App\Enums;

enum eRole: string
{
    case ADMIN = 'Admin';
    case CUSTOMER = 'Customer';
    case SUPER_ADMIN = 'Super Admin';
    case INSTITUTION_ADMIN = 'Institution Admin';
    case MINISTRY_OF_EDUCATION = 'Ministry of Education';

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
     * Roles an institution's own admins may assign to their institution's admin users.
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
        return [self::SUPER_ADMIN->value, self::MINISTRY_OF_EDUCATION->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
