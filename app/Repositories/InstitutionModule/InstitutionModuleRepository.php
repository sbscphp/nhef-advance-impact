<?php

namespace App\Repositories\InstitutionModule;

use App\Models\Institution;
use App\Models\InstitutionModule;
use App\Repositories\Contracts\InstitutionModule\InstitutionModuleRepositoryInterface;
use Illuminate\Support\Collection;

class InstitutionModuleRepository implements InstitutionModuleRepositoryInterface
{
    public function syncForInstitution(Institution $institution, array $moduleKeys): void
    {
        $institution->modules()->delete();

        $institution->modules()->createMany(array_map(
            fn (string $moduleKey): array => ['module_key' => $moduleKey],
            $moduleKeys,
        ));
    }

    public function keysForInstitution(int $institutionId): Collection
    {
        return InstitutionModule::query()
            ->where('institution_id', $institutionId)
            ->pluck('module_key');
    }
}
