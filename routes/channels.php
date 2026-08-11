<?php

use App\Models\User;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel() invokes this closure directly as ($user, ...$patternParams), with no
// container injection for extra parameters (unlike route closures) - resolve the repository
// from the container inside the body instead of via a type-hinted parameter.
Broadcast::channel('networking.channel.{channelUuid}', function (User $user, string $channelUuid) {
    $channels = app(NetworkingChannelRepositoryInterface::class);
    $channel = $channels->findByUuid($channelUuid);

    if ($channel === null) {
        return false;
    }

    return $channels->isMember($channel->id, $user->id);
});
