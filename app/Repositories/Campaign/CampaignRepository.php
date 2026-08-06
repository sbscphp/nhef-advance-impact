<?php

namespace App\Repositories\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginateActive(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->active()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('goal_amount', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
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

    public function slugExists(string $slug): bool
    {
        return Campaign::query()->where('slug', $slug)->exists();
    }

    public function create(array $data): Campaign
    {
        return Campaign::query()->create($data);
    }
}
