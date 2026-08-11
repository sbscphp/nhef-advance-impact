<?php

namespace App\Repositories\Contracts\Networking;

use App\Models\NetworkingMessage;
use Illuminate\Pagination\LengthAwarePaginator;

interface NetworkingMessageRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): NetworkingMessage;

    public function findByUuid(string $uuid): ?NetworkingMessage;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForChannel(int $channelId, array $filters, int $perPage): LengthAwarePaginator;
}
