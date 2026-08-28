<?php

namespace App\Repositories\Crm;

use App\Enums\ProposalRecipientStatusEnum;
use App\Models\ProposalRecipient;
use App\Repositories\Contracts\Crm\ProposalRecipientRepositoryInterface;
use Illuminate\Support\Collection;

class ProposalRecipientRepository implements ProposalRecipientRepositoryInterface
{
    public function replaceForProposal(int $proposalId, array $emails): Collection
    {
        ProposalRecipient::query()->where('proposal_id', $proposalId)->delete();

        return collect($emails)->map(fn (string $email) => ProposalRecipient::create([
            'proposal_id' => $proposalId,
            'email' => $email,
            'status' => ProposalRecipientStatusEnum::PENDING->value,
        ]));
    }

    public function update(ProposalRecipient $recipient, array $data): ProposalRecipient
    {
        $recipient->forceFill($data)->save();

        return $recipient;
    }

    public function listForProposal(int $proposalId): Collection
    {
        return ProposalRecipient::query()
            ->where('proposal_id', $proposalId)
            ->orderBy('id')
            ->get();
    }
}
