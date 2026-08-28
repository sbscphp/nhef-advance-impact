<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProspectProposal;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProspectProposalRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProspectProposal;

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectProposal;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProspectProposal $proposal, array $data): ProspectProposal;

    public function delete(ProspectProposal $proposal): void;

    /** Powers "Copy (01)", "Copy (02)", ... duplicate titles. */
    public function countByTitlePrefix(int $prospectId, string $prefix): int;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator;
}
