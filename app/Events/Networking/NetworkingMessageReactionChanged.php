<?php

namespace App\Events\Networking;

use App\Models\NetworkingMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NetworkingMessageReactionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public NetworkingMessage $message, public string $channelUuid) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('networking.channel.'.$this->channelUuid)];
    }

    public function broadcastAs(): string
    {
        return 'message.reaction.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $reactions = $this->message->reactions()->with('user')->get();

        return [
            'message_uuid' => $this->message->uuid,
            'reactions' => $reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'user_uuids' => $group->pluck('user.uuid')->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
