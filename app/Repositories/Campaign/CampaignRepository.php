<?php

namespace App\Repositories\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginateActive(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->active()
            ->with('projects')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
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

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['allocatedAdmin', 'bankAccount.bank', 'creator', 'projects', 'nationalGivingDayOpening', 'campaignInstitutions'])
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
            )
            ->when(
                filled($filters['filters']['institution_uuid'] ?? null),
                fn ($query) => $query->whereHas(
                    'campaignInstitutions.institution',
                    fn ($q) => $q->where('institutions.uuid', $filters['filters']['institution_uuid'])
                )
            )
            ->when(
                filled($filters['filters']['opening_uuid'] ?? null),
                fn ($query) => $query->whereHas(
                    'nationalGivingDayOpening',
                    fn ($q) => $q->where('national_giving_day_openings.uuid', $filters['filters']['opening_uuid'])
                )
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
            ->with(['projects', 'allocatedAdmin'])
            ->where('status', CampaignStatusEnum::ACTIVE->value)
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()
            ->with(['allocatedAdmin', 'bankAccount.bank', 'creator', 'projects', 'nationalGivingDayOpening', 'campaignInstitutions'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function incrementRaisedAmount(Campaign $campaign, string $amount): Campaign
    {
        $campaign->forceFill(['raised_amount' => (float) $campaign->raised_amount + (float) $amount])->save();

        return $campaign;
    }

    /** Slugs are unique platform-wide, not per institution, so this ignores the tenant scope. */
    public function slugExists(string $slug): bool
    {
        return Campaign::query()->withoutGlobalScope(TenantScope::class)->where('slug', $slug)->exists();
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

    public function countDistinctDonorsForCampaigns(array $campaignIds): int
    {
        if ($campaignIds === []) {
            return 0;
        }

        return (int) DB::table('donations')
            ->whereIn('campaign_id', $campaignIds)
            ->where('total_received', '>', 0)
            ->distinct()
            ->count(DB::raw('COALESCE(user_id, guest_email)'));
    }

    public function countActive(): int
    {
        return Campaign::query()->active()->count();
    }

    /**
     * Every campaign regardless of status - matches the unfiltered campaign list's own total, so
     * the Fundraising overview card can show a number that actually ties out with the list below
     * it (client feedback, 2026-10-16: "Active Campaigns" alone never tallied with the list, since
     * that card only ever counted active-status campaigns while the list includes every status).
     */
    public function countTotal(?string $type = null): int
    {
        return Campaign::query()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->count();
    }

    public function countOngoing(?string $type = null): int
    {
        return Campaign::query()
            ->active()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->count();
    }
}
