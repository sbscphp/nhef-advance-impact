<?php

namespace App\Http\Resources\Crm;

use App\Models\ProposalRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProposalRecipient */
class ProposalRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'last_attempted_at' => $this->last_attempted_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'last_error' => $this->last_error,
        ];
    }
}
