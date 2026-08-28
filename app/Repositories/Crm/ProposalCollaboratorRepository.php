<?php

namespace App\Repositories\Crm;

use App\Models\ProposalCollaborator;
use App\Repositories\Contracts\Crm\ProposalCollaboratorRepositoryInterface;
use Illuminate\Support\Collection;

class ProposalCollaboratorRepository implements ProposalCollaboratorRepositoryInterface
{
    public function create(array $data): ProposalCollaborator
    {
        return ProposalCollaborator::create($data);
    }

    public function update(ProposalCollaborator $collaborator, array $data): ProposalCollaborator
    {
        $collaborator->forceFill($data)->save();

        return $collaborator;
    }

    public function findForProposalAndAdmin(int $proposalId, int $adminId): ?ProposalCollaborator
    {
        return ProposalCollaborator::query()
            ->where('proposal_id', $proposalId)
            ->where('admin_id', $adminId)
            ->first();
    }

    public function listForProposal(int $proposalId): Collection
    {
        return ProposalCollaborator::query()
            ->with(['admin', 'inviter'])
            ->where('proposal_id', $proposalId)
            ->orderBy('created_at')
            ->get();
    }
}
