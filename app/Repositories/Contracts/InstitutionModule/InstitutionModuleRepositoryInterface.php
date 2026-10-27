<?php

namespace App\Repositories\Contracts\InstitutionModule;

use App\Models\Institution;
use Illuminate\Support\Collection;

interface InstitutionModuleRepositoryInterface
{
    /**
     * Replaces the institution's full module set with the given keys.
     *
     * @param  list<string>  $moduleKeys
     */
    public function syncForInstitution(Institution $institution, array $moduleKeys): void;

    /**
     * @return Collection<int, string>
     */
    public function keysForInstitution(int $institutionId): Collection;
}
