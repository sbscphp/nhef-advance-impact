<?php

namespace App\Events\Networking;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ephemeral, never persisted. Dispatched via ShouldBroadcastNow (not queued) so the "user is
 * typing" signal reaches other members immediately instead of waiting on a queue worker.
 */
class NetworkingTypingSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $channelUuid, public User $user) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('networking.channel.'.$this->channelUuid)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_uuid' => $this->user->uuid,
            'display_name' => $this->user->displayName(),
        ];
    }
}
