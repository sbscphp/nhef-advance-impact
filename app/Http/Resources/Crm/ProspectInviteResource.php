<?php

namespace App\Http\Resources\Crm;

use App\Models\ProspectInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProspectInvite */
class ProspectInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'invite_type' => $this->invite_type,
            'virtual_link' => $this->virtual_link,
            'venue' => $this->venue,
            'sent_by' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
