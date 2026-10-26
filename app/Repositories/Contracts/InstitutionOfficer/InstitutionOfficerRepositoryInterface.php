<?php

namespace App\Repositories\Contracts\InstitutionOfficer;

use App\Models\Institution;
use App\Models\InstitutionOfficer;
use Illuminate\Support\Collection;

interface InstitutionOfficerRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Institution $institution, array $data): InstitutionOfficer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InstitutionOfficer $officer, array $data): InstitutionOfficer;

    public function delete(InstitutionOfficer $officer): void;

    public function findForInstitution(int $institutionId, string $uuid): ?InstitutionOfficer;

    /**
     * @return Collection<int, InstitutionOfficer>
     */
    public function allForInstitution(int $institutionId): Collection;
}
