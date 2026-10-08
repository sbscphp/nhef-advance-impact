<?php

namespace App\Repositories\CampaignInstitution;

use App\Enums\CampaignStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Models\CampaignInstitution;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CampaignInstitutionRepository implements CampaignInstitutionRepositoryInterface
{
    public function createMany(Campaign $campaign, array $rows): void
    {
        foreach ($rows as $row) {
            $this->create($campaign, $row);
        }
    }

    public function create(Campaign $campaign, array $data): CampaignInstitution
    {
        return $campaign->campaignInstitutions()->create($data);
    }

    public function allForCampaign(int $campaignId): Collection
    {
        return CampaignInstitution::query()
            ->with('institution')
            ->where('campaign_id', $campaignId)
            ->get();
    }

    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CampaignInstitution::query()
            ->select('campaign_institutions.*')
            ->with(['institution', 'bankAccount.bank'])
            ->where('campaign_institutions.campaign_id', $campaignId)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('institution', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'campaign_institutions.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('institutions', 'institutions.id', '=', 'campaign_institutions.institution_id')
                ->orderBy('institutions.name', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('campaign_institutions.goal_amount', $direction),
        ], 'campaign_institutions.created_at');

        return $query->paginate($perPage);
    }

    public function paginateForInstitution(int $institutionId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CampaignInstitution::query()
            ->select('campaign_institutions.*')
            ->with(['campaign', 'institution'])
            ->where('campaign_institutions.institution_id', $institutionId)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('campaign', fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'campaign_institutions.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('campaigns', 'campaigns.id', '=', 'campaign_institutions.campaign_id')
                ->orderBy('campaigns.title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('campaign_institutions.goal_amount', $direction),
        ], 'campaign_institutions.created_at');

        return $query->paginate($perPage);
    }

    public function findForCampaign(int $campaignId, string $uuid): ?CampaignInstitution
    {
        return CampaignInstitution::query()
            ->with(['institution', 'bankAccount.bank'])
            ->where('campaign_id', $campaignId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(CampaignInstitution $campaignInstitution, array $data): CampaignInstitution
    {
        $campaignInstitution->fill($data)->save();

        return $campaignInstitution;
    }

    public function delete(CampaignInstitution $campaignInstitution): void
    {
        $campaignInstitution->delete();
    }

    public function countByInstitutions(array $institutionIds): array
    {
        if ($institutionIds === []) {
            return [];
        }

        return CampaignInstitution::query()
            ->whereIn('institution_id', $institutionIds)
            ->groupBy('institution_id')
            ->selectRaw('institution_id, count(*) as total')
            ->pluck('total', 'institution_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function countActiveByInstitutions(array $institutionIds): array
    {
        if ($institutionIds === []) {
            return [];
        }

        return CampaignInstitution::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_institutions.campaign_id')
            ->whereIn('campaign_institutions.institution_id', $institutionIds)
            ->where('campaigns.status', CampaignStatusEnum::ACTIVE->value)
            ->groupBy('campaign_institutions.institution_id')
            ->selectRaw('campaign_institutions.institution_id, count(*) as total')
            ->pluck('total', 'institution_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function countByCampaigns(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return CampaignInstitution::query()
            ->whereIn('campaign_id', $campaignIds)
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, count(*) as total')
            ->pluck('total', 'campaign_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function namesByCampaigns(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return CampaignInstitution::query()
            ->whereIn('campaign_id', $campaignIds)
            ->with('institution')
            ->get()
            ->filter(fn (CampaignInstitution $row) => $row->institution !== null)
            ->groupBy('campaign_id')
            ->map(fn (Collection $rows) => $rows->map(fn (CampaignInstitution $row) => [
                'uuid' => $row->institution->uuid,
                'name' => $row->institution->name,
            ])->values()->all())
            ->all();
    }

    public function exportForCampaign(int $campaignId, array $filters): Collection
    {
        return CampaignInstitution::query()
            ->with(['institution', 'bankAccount.bank'])
            ->where('campaign_id', $campaignId)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('institution', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            )
            ->orderBy('id')
            ->limit(5000)
            ->get();
    }

    public function paginateActiveTracking(array $filters, int $perPage): LengthAwarePaginator
    {
        $window = ListingFilterRules::resolveDateWindow($filters);

        return CampaignInstitution::query()
            ->select('campaign_institutions.*')
            ->with(['campaign', 'institution'])
            ->join('campaigns', 'campaigns.id', '=', 'campaign_institutions.campaign_id')
            ->where('campaigns.status', CampaignStatusEnum::ACTIVE->value)
            ->when($window['start'] !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('campaigns.ends_at')->orWhere('campaigns.ends_at', '>=', $window['start'])))
            ->when($window['end'] !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('campaigns.starts_at')->orWhere('campaigns.starts_at', '<=', $window['end'])))
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $inner->where('campaigns.title', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('institution', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'));
                })
            )
            ->orderByDesc('campaigns.starts_at')
            ->orderBy('campaign_institutions.id')
            ->paginate($perPage);
    }
}
