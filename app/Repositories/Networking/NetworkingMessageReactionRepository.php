<?php

namespace App\Repositories\Networking;

use App\Models\NetworkingMessage;
use App\Models\NetworkingMessageReaction;
use App\Repositories\Contracts\Networking\NetworkingMessageReactionRepositoryInterface;

class NetworkingMessageReactionRepository implements NetworkingMessageReactionRepositoryInterface
{
    public function findForUser(int $messageId, int $userId, string $emoji): ?NetworkingMessageReaction
    {
        return NetworkingMessageReaction::query()
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();
    }

    public function add(NetworkingMessage $message, int $userId, string $emoji): NetworkingMessageReaction
    {
        return NetworkingMessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $userId,
            'emoji' => $emoji,
        ]);
    }

    public function remove(NetworkingMessage $message, int $userId, string $emoji): void
    {
        NetworkingMessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->delete();
    }
}
