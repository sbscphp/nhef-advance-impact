<?php

use App\Models\User;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use Illuminate\Support\Facades\Broadcast;

// No container injection for extra closure params here (unlike route closures) - resolve the
// repository inside the body instead of via a type-hinted parameter, or this throws ArgumentCountError.
Broadcast::channel('networking.channel.{channelUuid}', function (User $user, string $channelUuid) {
    $channels = app(NetworkingChannelRepositoryInterface::class);
    $channel = $channels->findByUuid($channelUuid);

    if ($channel === null) {
        return false;
    }

    return $channels->isMember($channel->id, $user->id);
});
