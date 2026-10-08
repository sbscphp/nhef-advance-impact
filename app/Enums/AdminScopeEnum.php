<?php

namespace App\Enums;

use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which side of the platform an admin account belongs to. It is derived, never stored:
 * an account with no institution is NHEF staff, one with an institution is that institution's.
 */
enum AdminScopeEnum: string
{
    case NHEF = 'nhef';
    case INSTITUTION = 'institution';

    public static function of(Admin $admin): self
    {
        return $admin->institution_id === null ? self::NHEF : self::INSTITUTION;
    }

    /** The scope of the request being served: institution admins run inside their institution's tenant context. */
    public static function current(): self
    {
        return Institution::checkCurrent() ? self::INSTITUTION : self::NHEF;
    }

    /** The Institution Admin role belongs to institution scope, every other role to NHEF scope. */
    public function allowsRole(string $roleName): bool
    {
        $isInstitutionRole = in_array($roleName, eRole::institutionAssignable(), true);

        return $this === self::INSTITUTION ? $isInstitutionRole : ! $isInstitutionRole;
    }

    /**
     * @param  Builder<\App\Models\Role>  $query
     * @return Builder<\App\Models\Role>
     */
    public function constrainRoles(Builder $query): Builder
    {
        return $this === self::INSTITUTION
            ? $query->whereIn('name', eRole::institutionAssignable())
            : $query->whereNotIn('name', eRole::institutionAssignable());
    }
}
