<?php

namespace App\Http\Resources\Networking;

use App\Models\NetworkingChannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkingChannel
 *
 * For direct channels, set the `other_member` (User) attribute on the model before wrapping it
 * in this resource, since a direct channel has no name of its own {@see
 * \App\Services\Networking\NetworkingService::attachDirectChannelDisplayData()}. Its `description`
 * is a system-generated "Direct message between X and Y" string set at creation time, unlike
 * community/forum channels where it's admin-authored.
 */
class NetworkingChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;
        $otherMember = $this->other_member ?? null;

        $lastMessage = $this->whenLoaded('latestMessage', fn () => NetworkingMessageResource::make(
            $this->latestMessage->loadMissing('sender')
        ));

        $viewerLastReadAt = $this->relationLoaded('members') && $this->members->isNotEmpty()
            ? $this->members->first()->pivot->last_read_at
            : null;

        $isUnread = $this->relationLoaded('latestMessage')
            && $this->latestMessage !== null
            && $this->latestMessage->sender_id !== $viewerId
            && ($viewerLastReadAt === null || $this->latestMessage->created_at->gt($viewerLastReadAt));

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->isDirect() ? $otherMember?->displayName() : $this->name,
            'description' => $this->description,
            'avatar_url' => $this->avatar_url,
            'member_count' => $this->when(isset($this->members_count), fn () => $this->members_count),
            'is_member' => $this->when($this->viewer_is_member !== null, fn () => (bool) $this->viewer_is_member),
            'is_archived' => $this->is_archived,
            'is_locked' => $this->is_locked,
            'created_by_admin' => $this->whenLoaded('createdByAdmin', fn () => $this->createdByAdmin === null ? null : [
                'uuid' => $this->createdByAdmin->uuid,
                'display_name' => $this->createdByAdmin->displayName(),
            ]),
            'last_message' => $lastMessage,
            'is_unread' => $this->when($this->relationLoaded('latestMessage'), fn () => $isUnread),
            'other_member' => $this->when($otherMember instanceof User, fn () => [
                'uuid' => $otherMember->uuid,
                'display_name' => $otherMember->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
