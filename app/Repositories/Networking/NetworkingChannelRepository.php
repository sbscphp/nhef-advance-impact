<?php

namespace App\Repositories\Networking;

use App\Enums\NetworkingChannelTypeEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\NetworkingChannel;
use App\Models\User;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NetworkingChannelRepository implements NetworkingChannelRepositoryInterface
{
    public function create(array $data): NetworkingChannel
    {
        return NetworkingChannel::create($data);
    }

    public function findByUuid(string $uuid): ?NetworkingChannel
    {
        return NetworkingChannel::query()
            ->withCount('members')
            ->where('uuid', $uuid)
            ->first();
    }

    public function findDirectBetween(int $userId, int $otherUserId): ?NetworkingChannel
    {
        return NetworkingChannel::query()
            ->where('type', NetworkingChannelTypeEnum::DIRECT->value)
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->whereHas('members', fn ($q) => $q->where('users.id', $otherUserId))
            ->first();
    }

    public function otherDirectMember(int $channelId, int $viewerId): ?User
    {
        return User::query()
            ->join('networking_channel_members', 'networking_channel_members.user_id', '=', 'users.id')
            ->where('networking_channel_members.channel_id', $channelId)
            ->where('users.id', '!=', $viewerId)
            ->whereNull('networking_channel_members.left_at')
            ->select('users.*')
            ->first();
    }

    public function paginateConversationsForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = NetworkingChannel::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->with(['members' => fn ($q) => $q->where('users.id', $userId), 'latestMessage.sender'])
            ->withCount('members')
            ->withMax('messages as last_activity_at', 'created_at');

        if (isset($filters['type']) && in_array($filters['type'], NetworkingChannelTypeEnum::values(), true)) {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderByDesc(DB::raw('COALESCE(last_activity_at, networking_channels.created_at)'))
            ->paginate($perPage);
    }

    public function paginateBrowsable(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = NetworkingChannel::query()
            ->whereIn('type', NetworkingChannelTypeEnum::browsableValues())
            ->where('is_archived', false)
            ->withCount('members');

        if (isset($filters['type']) && in_array($filters['type'], NetworkingChannelTypeEnum::browsableValues(), true)) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');
        ListingFilterRules::applySort($query, $filters, self::channelSortMap(), 'name', 'asc');

        return $query->paginate($perPage);
    }

    public function isMember(int $channelId, int $userId): bool
    {
        return DB::table('networking_channel_members')
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    public function memberChannelIdsForUser(int $userId): array
    {
        return DB::table('networking_channel_members')
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->pluck('channel_id')
            ->all();
    }

    public function addMember(NetworkingChannel $channel, int $userId): bool
    {
        $existing = DB::table('networking_channel_members')
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing === null) {
            DB::table('networking_channel_members')->insert([
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'joined_at' => now(),
                'left_at' => null,
                'last_read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }

        if ($existing->left_at === null) {
            return false;
        }

        DB::table('networking_channel_members')
            ->where('id', $existing->id)
            ->update(['left_at' => null, 'joined_at' => now(), 'updated_at' => now()]);

        return true;
    }

    public function removeMember(NetworkingChannel $channel, int $userId): bool
    {
        $updated = DB::table('networking_channel_members')
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->update(['left_at' => now(), 'updated_at' => now()]);

        return $updated > 0;
    }

    public function touchLastRead(NetworkingChannel $channel, int $userId): void
    {
        DB::table('networking_channel_members')
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = NetworkingChannel::query()
            ->whereIn('type', NetworkingChannelTypeEnum::browsableValues())
            ->withCount('members')
            ->with('createdByAdmin');

        if (isset($filters['type']) && in_array($filters['type'], NetworkingChannelTypeEnum::browsableValues(), true)) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');
        ListingFilterRules::applySort($query, $filters, self::channelSortMap(), 'created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, \Closure(\Illuminate\Database\Eloquent\Builder, string): void>
     */
    private static function channelSortMap(): array
    {
        return [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
            'created_at' => fn ($query, string $direction) => $query->orderBy('created_at', $direction),
            'members_count' => fn ($query, string $direction) => $query->orderBy('members_count', $direction),
        ];
    }

    public function update(NetworkingChannel $channel, array $data): NetworkingChannel
    {
        $channel->update($data);

        return $channel->refresh();
    }

    public function delete(NetworkingChannel $channel): void
    {
        $channel->delete();
    }
}
