<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProposalCollaborator;
use Illuminate\Support\Collection;

interface ProposalCollaboratorRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProposalCollaborator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProposalCollaborator $collaborator, array $data): ProposalCollaborator;

    public function findForProposalAndAdmin(int $proposalId, int $adminId): ?ProposalCollaborator;

    /**
     * @return Collection<int, ProposalCollaborator>
     */
    public function listForProposal(int $proposalId): Collection;
}
