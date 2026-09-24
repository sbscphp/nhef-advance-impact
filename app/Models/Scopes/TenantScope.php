<?php

namespace App\Models\Scopes;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = Institution::current();

        if ($tenant === null) {
            return;
        }

        $model::constrainToTenant($builder, $tenant);
    }
}
