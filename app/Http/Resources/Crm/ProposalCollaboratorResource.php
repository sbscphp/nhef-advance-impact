<?php

namespace App\Http\Resources\Crm;

use App\Models\ProposalCollaborator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProposalCollaborator */
class ProposalCollaboratorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role,
            'admin' => $this->whenLoaded('admin', fn () => $this->admin === null ? null : [
                'uuid' => $this->admin->uuid,
                'name' => $this->admin->displayName(),
                'email' => $this->admin->email,
            ]),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter === null ? null : [
                'uuid' => $this->inviter->uuid,
                'name' => $this->inviter->displayName(),
            ]),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
