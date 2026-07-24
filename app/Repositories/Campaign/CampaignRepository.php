<?php

namespace App\Repositories\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Models\Campaign;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginateActive(array $filters, int $perPage): LengthAwarePaginator
    {
        return Campaign::query()
            ->active()
            ->when(
                filled($filters['category'] ?? null),
                fn ($query) => $query->where('category', $filters['category'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findActiveByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()
            ->where('status', CampaignStatusEnum::ACTIVE->value)
            ->where('uuid', $uuid)
            ->first();
    }

    public function incrementRaisedAmount(Campaign $campaign, string $amount): Campaign
    {
        $campaign->forceFill(['raised_amount' => (float) $campaign->raised_amount + (float) $amount])->save();

        return $campaign;
    }
}
