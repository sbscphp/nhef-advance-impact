<?php

namespace App\Repositories\Contracts\Admin;

use App\Models\Admin;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Collection;

interface AdminRepositoryInterface
{
    public function findByUuid(string $uuid): ?Admin;

    /** Emails are unique across NHEF and every institution, so this ignores tenant scoping. */
    public function emailExists(string $email): bool;

    /** The institution's first admin, created without a usable password until they set one via the invite link. */
    public function createInstitutionOwner(Institution $institution, string $name, string $email): Admin;

    /**
     * Active, login-enabled admins for an "Assigned To" picker.
     *
     * @return Collection<int, Admin>
     */
    public function listActive(): Collection;

    /**
     * Active, login-enabled admin uuids belonging to any of the given institutions - for
     * notifying every institution added to a National Giving Day campaign.
     *
     * @param  list<int>  $institutionIds
     * @return list<string>
     */
    public function uuidsForInstitutions(array $institutionIds): array;
}
