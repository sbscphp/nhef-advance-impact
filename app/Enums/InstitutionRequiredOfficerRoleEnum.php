<?php

namespace App\Enums;

/**
 * The three offices every institution's `officers` array must include on invite (2026-10-26,
 * replacing the earlier fixed vc_name/advancement_director_name/advancement_database_manager_name
 * columns) - any further entries in that same array are free-typed custom officers, not
 * validated against this list.
 */
enum InstitutionRequiredOfficerRoleEnum: string
{
    case VC = 'vc';
    case ADVANCEMENT_DIRECTOR = 'advancement_director';
    case ADVANCEMENT_DATABASE_MANAGER = 'advancement_database_manager';

    public function label(): string
    {
        return match ($this) {
            self::VC => 'Vice Chancellor',
            self::ADVANCEMENT_DIRECTOR => 'Advancement Director',
            self::ADVANCEMENT_DATABASE_MANAGER => 'Advancement Database Manager',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
