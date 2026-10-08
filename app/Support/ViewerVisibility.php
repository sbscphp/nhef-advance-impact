<?php

namespace App\Support;

use App\Enums\ePermission;
use App\Models\Admin;

/**
 * What the signed-in admin is allowed to see, driven by the two visibility permissions so a
 * Super Admin can change it by editing a role. Anyone who is not an admin (customers, public
 * pages, console jobs) is unrestricted here; their own rules apply elsewhere.
 */
final class ViewerVisibility
{
    public static function canSeeMoney(): bool
    {
        return self::allows(ePermission::VISIBILITY_MONETARY);
    }

    public static function canSeeIndividualRecords(): bool
    {
        return self::allows(ePermission::VISIBILITY_INDIVIDUAL_RECORDS);
    }

    /**
     * Fields to spread into a payload: the given fields when the viewer may see institution-level money, none otherwise.
     *
     * @template T of array<string, mixed>
     *
     * @param  T  $fields
     * @return T|array{}
     */
    public static function money(array $fields): array
    {
        return self::canSeeMoney() ? $fields : [];
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  T  $fields
     * @return T|array{}
     */
    public static function individual(array $fields): array
    {
        return self::canSeeIndividualRecords() ? $fields : [];
    }

    /**
     * Sent with an admin's profile and login response so a client can hide the matching columns and screens up front.
     *
     * @return array{monetary: bool, individual_records: bool}
     */
    public static function flagsFor(Admin $admin): array
    {
        return [
            'monetary' => $admin->checkPermissionTo(ePermission::VISIBILITY_MONETARY->value),
            'individual_records' => $admin->checkPermissionTo(ePermission::VISIBILITY_INDIVIDUAL_RECORDS->value),
        ];
    }

    private static function allows(ePermission $permission): bool
    {
        $actor = request()->user();

        return ! $actor instanceof Admin || $actor->checkPermissionTo($permission->value);
    }
}
