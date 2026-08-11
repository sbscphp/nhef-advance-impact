<?php

namespace App\Repositories\Contracts\Networking;

use App\Models\NetworkingChannel;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface NetworkingChannelRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): NetworkingChannel;

    public function findByUuid(string $uuid): ?NetworkingChannel;

    public function findDirectBetween(int $userId, int $otherUserId): ?NetworkingChannel;

    /**
     * The other participant in a direct channel, used to derive its display name/profile since
     * a direct channel has no name of its own.
     */
    public function otherDirectMember(int $channelId, int $viewerId): ?User;

    /**
     * Channels the user is an active member of (direct, community, or forum), most recent
     * activity first.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateConversationsForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Non-archived community/forum channels open for discovery, regardless of membership.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateBrowsable(array $filters, int $perPage): LengthAwarePaginator;

    public function isMember(int $channelId, int $userId): bool;

    /**
     * @return list<int>
     */
    public function memberChannelIdsForUser(int $userId): array;

    /**
     * @return bool true if this call changed membership (new join or rejoin), false if the user
     *               was already an active member.
     */
    public function addMember(NetworkingChannel $channel, int $userId): bool;

    /**
     * @return bool true if an active membership was removed, false if the user was not an
     *               active member to begin with.
     */
    public function removeMember(NetworkingChannel $channel, int $userId): bool;

    public function touchLastRead(NetworkingChannel $channel, int $userId): void;

    /**
     * Community/Forum channels only (never direct), for the admin "View & Manage
     * Community/Forums" screen; includes archived/locked channels, unlike {@see paginateBrowsable()}.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(NetworkingChannel $channel, array $data): NetworkingChannel;

    public function delete(NetworkingChannel $channel): void;
}
