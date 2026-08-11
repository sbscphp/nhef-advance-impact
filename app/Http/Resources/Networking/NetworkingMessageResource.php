<?php

namespace App\Http\Resources\Networking;

use App\Models\NetworkingMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NetworkingMessage */
class NetworkingMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'uuid' => $this->uuid,
            'sender' => $this->whenLoaded('sender', fn () => [
                'uuid' => $this->sender->uuid,
                'display_name' => $this->sender->displayName(),
            ]),
            'body' => $this->body,
            'attachment' => $this->when($this->hasAttachment(), fn () => [
                'url' => $this->attachment_url,
                'name' => $this->attachment_name,
                'mime' => $this->attachment_mime,
                'size' => $this->attachment_size,
            ]),
            'reactions' => $this->whenLoaded('reactions', fn () => $this->reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'reacted_by_me' => $viewerId !== null && $group->contains('user_id', $viewerId),
                ])
                ->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
