<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProspectMessage;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProspectMessageRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProspectMessage;

    public function findByUuid(string $uuid): ?ProspectMessage;

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectMessage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProspectMessage $message, array $data): ProspectMessage;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator;
}
