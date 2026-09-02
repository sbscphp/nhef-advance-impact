<?php

namespace App\Repositories\Pledge;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Pledge;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PledgeRepository implements PledgeRepositoryInterface
{
    public function create(array $data): Pledge
    {
        return Pledge::create($data);
    }

    public function findByUuid(string $uuid): ?Pledge
    {
        return Pledge::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidForUser(int $userId, string $uuid): ?Pledge
    {
        return Pledge::query()
            ->with(['user', 'campaign', 'installments' => fn ($query) => $query->orderBy('sequence'), 'payments'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Pledge::query()
            ->select('pledges.*')
            ->with(['user', 'campaign'])
            ->where('pledges.user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('pledges.status', $filters['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'pledges.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('campaigns', 'campaigns.id', '=', 'pledges.campaign_id')
                ->orderBy('campaigns.title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('pledges.total_amount', $direction),
        ], 'pledges.created_at');

        return $query->paginate($perPage);
    }

    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Pledge::query()
            ->select('pledges.*')
            ->with(['user', 'nextPendingInstallment'])
            ->where('pledges.campaign_id', $campaignId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('pledges.status', $filters['status'])
            )
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('pledges.guest_name', 'like', '%'.$search.'%')
                        ->orWhere('pledges.guest_email', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'pledges.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('users', 'users.id', '=', 'pledges.user_id')
                ->orderBy('users.firstname', $direction)
                ->orderBy('users.lastname', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('pledges.total_amount', $direction),
        ], 'pledges.created_at');

        return $query->paginate($perPage);
    }

    public function update(Pledge $pledge, array $data): Pledge
    {
        $pledge->forceFill($data)->save();

        return $pledge;
    }

    public function incrementAmountPaid(Pledge $pledge, string $amount): Pledge
    {
        $pledge->forceFill(['amount_paid' => (float) $pledge->amount_paid + (float) $amount])->save();

        return $pledge;
    }

    public function loadFresh(Pledge $pledge, array $relations): Pledge
    {
        return $pledge->fresh($relations);
    }

    public function sumReceivedForCampaignAndUniversity(int $campaignId, string $university): string
    {
        return (string) Pledge::query()
            ->join('users', 'users.id', '=', 'pledges.user_id')
            ->where('pledges.campaign_id', $campaignId)
            ->where('users.university', $university)
            ->sum('pledges.amount_paid');
    }

    public function overviewForUser(int $userId): array
    {
        $query = Pledge::query()->where('user_id', $userId);

        return [
            'count' => (int) (clone $query)->count(),
            'total_pledged' => (string) (clone $query)->sum('total_amount'),
            'total_fulfilled' => (string) (clone $query)->sum('amount_paid'),
        ];
    }
}
