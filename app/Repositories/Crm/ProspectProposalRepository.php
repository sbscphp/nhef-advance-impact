<?php

namespace App\Repositories\Crm;

use App\Models\ProspectProposal;
use App\Repositories\Contracts\Crm\ProspectProposalRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectProposalRepository implements ProspectProposalRepositoryInterface
{
    public function create(array $data): ProspectProposal
    {
        return ProspectProposal::create($data);
    }

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectProposal
    {
        return ProspectProposal::query()
            ->with(['creator', 'sender', 'collaborators.admin', 'recipients'])
            ->where('prospect_id', $prospectId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(ProspectProposal $proposal, array $data): ProspectProposal
    {
        $proposal->forceFill($data)->save();

        return $proposal;
    }

    public function delete(ProspectProposal $proposal): void
    {
        $proposal->delete();
    }

    public function countByTitlePrefix(int $prospectId, string $prefix): int
    {
        return ProspectProposal::query()
            ->where('prospect_id', $prospectId)
            ->where('title', 'like', $prefix.'%')
            ->count();
    }

    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator
    {
        return ProspectProposal::query()
            ->with(['creator'])
            ->where('prospect_id', $prospectId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
