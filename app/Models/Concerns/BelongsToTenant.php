<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a model to the current institution (tenant). With no current tenant
 * (NHEF landlord context, customer and public requests) nothing is filtered.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    abstract public static function constrainToTenant(Builder $query, Institution $tenant): void;
}
