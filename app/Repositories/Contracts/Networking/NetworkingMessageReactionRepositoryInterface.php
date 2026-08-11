<?php

namespace App\Repositories\Contracts\Networking;

use App\Models\NetworkingMessage;
use App\Models\NetworkingMessageReaction;

interface NetworkingMessageReactionRepositoryInterface
{
    public function findForUser(int $messageId, int $userId, string $emoji): ?NetworkingMessageReaction;

    public function add(NetworkingMessage $message, int $userId, string $emoji): NetworkingMessageReaction;

    public function remove(NetworkingMessage $message, int $userId, string $emoji): void;
}
