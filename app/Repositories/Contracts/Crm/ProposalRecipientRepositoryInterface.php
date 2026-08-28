<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProposalRecipient;
use Illuminate\Support\Collection;

interface ProposalRecipientRepositoryInterface
{
    /**
     * Deletes any existing rows for the proposal and creates a fresh `pending` row per email.
     *
     * @param  list<string>  $emails
     * @return Collection<int, ProposalRecipient>
     */
    public function replaceForProposal(int $proposalId, array $emails): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProposalRecipient $recipient, array $data): ProposalRecipient;

    /**
     * @return Collection<int, ProposalRecipient>
     */
    public function listForProposal(int $proposalId): Collection;
}
