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
     * Roles an institution's own admins may assign to their institution's admin users.
     *
     * @return list<string>
     */
    public static function institutionAssignable(): array
    {
        return [self::INSTITUTION_ADMIN->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
