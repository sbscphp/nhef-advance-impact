<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProspectInvite;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProspectInviteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProspectInvite;

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectInvite;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator;
}
