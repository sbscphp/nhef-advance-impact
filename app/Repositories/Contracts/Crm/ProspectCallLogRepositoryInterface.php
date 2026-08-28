<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProspectCallLog;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProspectCallLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProspectCallLog;

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectCallLog;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator;
}
