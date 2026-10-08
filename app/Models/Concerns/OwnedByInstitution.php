<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;

/**
 * For admin-authored records that carry an explicit institution_id: scoped to the current
 * tenant, and stamped with it on create. Records made by NHEF (no tenant) keep a null owner.
 */
trait OwnedByInstitution
{
    use BelongsToTenant;

    public static function bootOwnedByInstitution(): void
    {
        static::creating(function ($model): void {
            if ($model->institution_id === null && ($tenant = Institution::current()) !== null) {
                $model->institution_id = $tenant->id;
            }
        });
    }

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->where($query->getModel()->getTable().'.institution_id', $tenant->id);
    }
}
