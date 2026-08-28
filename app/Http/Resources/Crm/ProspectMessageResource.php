<?php

namespace App\Http\Resources\Crm;

use App\Models\ProspectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProspectMessage */
class ProspectMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'body' => $this->body,
            'banner_url' => $this->banner_url,
            'send_at' => $this->send_at?->toIso8601String(),
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_by' => $this->whenLoaded('sender', fn () => $this->sender === null ? null : [
                'uuid' => $this->sender->uuid,
                'name' => $this->sender->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
