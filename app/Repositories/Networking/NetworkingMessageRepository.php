<?php

namespace App\Repositories\Networking;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\NetworkingMessage;
use App\Repositories\Contracts\Networking\NetworkingMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkingMessageRepository implements NetworkingMessageRepositoryInterface
{
    public function create(array $data): NetworkingMessage
    {
        return NetworkingMessage::create($data);
    }

    public function findByUuid(string $uuid): ?NetworkingMessage
    {
        return NetworkingMessage::query()
            ->with(['sender', 'reactions.user'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForChannel(int $channelId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = NetworkingMessage::query()
            ->with(['sender', 'reactions.user'])
            ->where('channel_id', $channelId);

        if (! empty($filters['search'])) {
            $query->where('body', 'like', '%'.$filters['search'].'%');
        }

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');
        ListingFilterRules::applySort($query, $filters, [], 'created_at', 'desc');

        return $query->paginate($perPage);
    }
}
