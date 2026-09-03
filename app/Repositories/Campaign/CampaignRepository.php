<?php

namespace App\Repositories\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['allocatedAdmin', 'bankAccount.bank', 'creator'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            )
            ->when(
                filled($filters['filters']['type'] ?? null),
                fn ($query) => $query->where('type', $filters['filters']['type'])
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

    public function findByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()
            ->with(['allocatedAdmin', 'bankAccount.bank', 'creator'])
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

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->fill($data)->save();

        return $campaign;
    }

    public function countDistinctDonors(Campaign $campaign): int
    {
        return (int) DB::table('donations')
            ->where('campaign_id', $campaign->id)
            ->where('total_received', '>', 0)
            ->distinct()
            ->count(DB::raw('COALESCE(user_id, guest_email)'));
    }
}
