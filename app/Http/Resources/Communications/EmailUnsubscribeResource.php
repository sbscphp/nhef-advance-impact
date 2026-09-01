<?php

namespace App\Http\Resources\Communications;

use App\Models\EmailUnsubscribe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailUnsubscribe */
class EmailUnsubscribeResource extends JsonResource
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
            'mail' => $this->whenLoaded('mail', fn () => $this->mail === null ? null : [
                'uuid' => $this->mail->uuid,
                'title' => $this->mail->title,
            ]),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
        ];
    }
}
