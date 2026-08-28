<?php

namespace App\Http\Resources\Crm;

use App\Models\ProspectProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProspectProposal */
class ProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'send_message_title' => $this->send_message_title,
            'send_message_body' => $this->send_message_body,
            'attachments' => $this->attachments ?? [],
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'sent_by' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->displayName(),
            ]),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'recipients' => $this->whenLoaded('recipients', fn () => ProposalRecipientResource::collection($this->recipients)->resolve()),
            'collaborators' => $this->whenLoaded('collaborators', fn () => ProposalCollaboratorResource::collection($this->collaborators)->resolve()),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
