<?php

namespace App\Http\Resources\Communications;

use App\Models\MailRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MailRecipient */
class MailRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'name' => $this->whenLoaded('user', fn () => $this->user?->displayName()),
            'status' => $this->status,
            'last_error' => $this->last_error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'open_count' => $this->open_count,
        ];
    }
}
