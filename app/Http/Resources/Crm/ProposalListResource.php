<?php

namespace App\Http\Resources\Crm;

use App\Models\ProspectProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Lighter payload for the Proposals tab list. @mixin ProspectProposal */
class ProposalListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'status' => $this->status,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
