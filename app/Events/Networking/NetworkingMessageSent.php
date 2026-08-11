<?php

namespace App\Events\Networking;

use App\Http\Resources\Networking\NetworkingMessageResource;
use App\Models\NetworkingMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NetworkingMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public NetworkingMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('networking.channel.'.$this->message->channel->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return NetworkingMessageResource::make($this->message->loadMissing(['sender', 'reactions.user']))->resolve();
    }
}
