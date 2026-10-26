<?php

namespace App\Repositories\InstitutionOfficer;

use App\Models\Institution;
use App\Models\InstitutionOfficer;
use App\Repositories\Contracts\InstitutionOfficer\InstitutionOfficerRepositoryInterface;
use Illuminate\Support\Collection;

class InstitutionOfficerRepository implements InstitutionOfficerRepositoryInterface
{
    public function create(Institution $institution, array $data): InstitutionOfficer
    {
        return $institution->officers()->create($data);
    }

    public function update(InstitutionOfficer $officer, array $data): InstitutionOfficer
    {
        $officer->fill($data)->save();

        return $officer;
    }

    public function delete(InstitutionOfficer $officer): void
    {
        $officer->delete();
    }

    public function findForInstitution(int $institutionId, string $uuid): ?InstitutionOfficer
    {
        return InstitutionOfficer::query()
            ->where('institution_id', $institutionId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function allForInstitution(int $institutionId): Collection
    {
        return InstitutionOfficer::query()
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->get();
    }
}
