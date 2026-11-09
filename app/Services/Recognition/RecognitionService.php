<?php

namespace App\Services\Recognition;

use App\Enums\ConstituentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Models\DonationPayment;
use App\Models\DonorTier;
use App\Models\User;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Donor recognition wall (BRD REC-01/REC-02), scoped to successful, NGN-only donation
 * payments (no FX-rate source to sum other currencies); anonymous donations never appear here.
 */
class RecognitionService
{
    public function __construct(
        private readonly DonorTierRepositoryInterface $donorTierRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function leaderboard(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $search = filled($filters['search'] ?? null) ? (string) $filters['search'] : null;

        // `rank` always means true position by total given across every donor, never this row's
        // position within whatever `sort_by` the caller asked this *page* to be displayed in -
        // so it's computed once, up front, independent of the query below.
        $trueRanks = $this->rankMap($this->baseAggregateQuery()->orderByDesc('total')->get());

        $query = $this->baseAggregateQuery($search);

        ListingFilterRules::applySort($query, $filters, [
            'rank' => fn ($q, string $dir) => $q->orderBy('total', $dir === 'asc' ? 'desc' : 'asc'),
            'total' => fn ($q, string $dir) => $q->orderBy('total', $dir),
            'donor_name' => fn ($q, string $dir) => $q
                ->leftJoin('users', 'users.id', '=', 'donation_payments.user_id')
                ->groupBy('users.id')
                ->orderBy('users.firstname', $dir)
                ->orderBy('users.lastname', $dir),
        ], 'total', 'desc');

        $paginator = $query->paginate($perPage);

        $users = User::query()
            ->with('tertiaryInstitution')
            ->whereIn('id', collect($paginator->items())->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $tiersByInstitution = $this->tiersForUsers($users);

        $entries = collect($paginator->items())->values()->map(function ($row) use ($users, $tiersByInstitution, $trueRanks) {
            $user = $users->get($row->user_id);
            $tier = $user !== null ? $this->tierFor($this->tiersForUser($user, $tiersByInstitution), (string) $row->total) : null;

            return (object) [
                'rank' => $trueRanks[$row->user_id] ?? null,
                'donor_name' => $user?->displayName() ?? 'Donor',
                'avatar_url' => $user?->profile_picture_url,
                'user_type' => $user?->constituent_type,
                'user_type_label' => ConstituentTypeEnum::tryFrom((string) $user?->constituent_type)?->label(),
                'tier' => $tier?->name,
                'tier_color' => $tier?->color,
                'total' => (string) $row->total,
            ];
        });

        $paginator->setCollection($entries);

        return $paginator;
    }

    /**
     * @param  Collection<int, object{user_id: int, total: string}>  $orderedByTotalDesc
     * @return array<int, int>
     */
    private function rankMap(Collection $orderedByTotalDesc): array
    {
        $ranks = [];
        foreach ($orderedByTotalDesc->values() as $index => $row) {
            $ranks[$row->user_id] = $index + 1;
        }

        return $ranks;
    }

    /**
     * @return array{rank: ?int, tier: ?string, tier_color: ?string, total: string, total_formatted: string, next_tier: ?string, amount_to_next_tier: ?string, amount_to_next_tier_formatted: ?string}
     */
    public function myRank(User $user): array
    {
        // The donor's own total counts everything they've given (anonymous gifts included;
        // it's their own money), but their rank is computed against the same publicly visible
        // pool the leaderboard itself uses, since that's what "rank" means on the public wall.
        $total = (string) DonationPayment::query()
            ->where('user_id', $user->id)
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->sum('amount');

        $tiers = $this->donorTierRepository->activeOrderedByThreshold($this->institutionIdFor($user));
        $currentTier = $this->tierFor($tiers, $total);
        $nextTier = $tiers->first(fn ($tier) => (float) $tier->minimum_amount > (float) $total);

        $higherCount = $this->baseAggregateQuery()
            ->havingRaw('SUM(donation_payments.amount) > ?', [$total])
            ->get()
            ->count();

        $amountToNextTier = $nextTier ? (string) ((float) $nextTier->minimum_amount - (float) $total) : null;

        return [
            'rank' => $total > 0 ? $higherCount + 1 : null,
            'tier' => $currentTier?->name,
            'tier_color' => $currentTier?->color,
            'total' => $total,
            'total_formatted' => Money::format($total, 'NGN'),
            'next_tier' => $nextTier?->name,
            'amount_to_next_tier' => $amountToNextTier,
            'amount_to_next_tier_formatted' => $amountToNextTier !== null ? Money::format($amountToNextTier, 'NGN') : null,
        ];
    }

    /**
     * One row per campaign this donor has contributed to - the "Recognition Wall" donor screen's
     * top-three cards (`sort_by=position`) and campaigns table (`sort_by=total`, search, date
     * range) both come from this same endpoint, just called with different query params.
     *
     * `position_change` is always null for now: there's no snapshot/history mechanism anywhere
     * in the codebase recording a donor's *previous* rank in a campaign to compare against - this
     * needs a product decision (e.g. a scheduled job periodically snapshotting ranks) before it
     * can mean anything real, flagged rather than fabricated.
     *
     * @param  array<string, mixed>  $filters
     */
    public function myCampaigns(User $user, array $filters): LengthAwarePaginator
    {
        $window = ListingFilterRules::resolveDateWindow($filters);
        $search = filled($filters['search'] ?? null) ? (string) $filters['search'] : null;

        $rows = $this->paymentRepository->campaignTotalsForUser(
            $user->id,
            $search,
            $window['start']?->toDateString(),
            $window['end']?->toDateString(),
        );

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $paginatorOptions = ['path' => request()->url(), 'query' => request()->query()];

        if ($rows->isEmpty()) {
            return new LengthAwarePaginator([], 0, $perPage, $page, $paginatorOptions);
        }

        $campaignIds = $rows->pluck('campaign_id')->map(fn ($id) => (int) $id)->all();
        $campaigns = $this->campaignRepository->findManyByIds($campaignIds)->keyBy('id');
        $donorsCounts = $this->paymentRepository->nonAnonymousDonorsCountByCampaigns($campaignIds);

        $lifetimeTotal = $this->paymentRepository->sumSuccessfulForUser($user->id, null, null);
        $tier = $this->tierFor($this->donorTierRepository->activeOrderedByThreshold($this->institutionIdFor($user)), $lifetimeTotal);

        $entries = $rows->map(function ($row) use ($user, $campaigns, $donorsCounts, $tier) {
            /** @var ?Campaign $campaign */
            $campaign = $campaigns->get((int) $row->campaign_id);

            return (object) [
                'campaign_uuid' => $campaign?->uuid,
                'campaign_name' => $campaign?->title,
                'tier' => $tier?->name,
                'total' => (string) $row->total,
                'position' => $this->paymentRepository->positionForUserInCampaign($user->id, (int) $row->campaign_id),
                'donors_count' => $donorsCounts[(int) $row->campaign_id] ?? 0,
                'position_change' => null,
            ];
        });

        $sortBy = is_string($filters['sort_by'] ?? null) ? $filters['sort_by'] : 'total';
        $direction = strtolower((string) ($filters['sort_direction'] ?? ($sortBy === 'position' ? 'asc' : 'desc'))) === 'asc' ? 'asc' : 'desc';

        $sorted = $sortBy === 'position'
            ? $entries->sortBy(fn ($entry) => $entry->position ?? PHP_INT_MAX, SORT_REGULAR, $direction === 'desc')
            : $entries->sortBy(fn ($entry) => (float) $entry->total, SORT_REGULAR, $direction === 'desc');

        $sorted = $sorted->values();

        return new LengthAwarePaginator(
            $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            $paginatorOptions,
        );
    }

    /**
     * Shared base for the leaderboard and myRank()'s rank-comparison query: one row per user,
     * their lifetime NGN total from successful, non-anonymous donations. Guests are excluded
     * automatically (no account to rank).
     */
    private function baseAggregateQuery(?string $search = null): Builder
    {
        $query = DonationPayment::query()
            ->select('donation_payments.user_id')
            ->selectRaw('SUM(donation_payments.amount) as total')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->where('donations.is_anonymous', false)
            ->whereNotNull('donation_payments.user_id')
            ->groupBy('donation_payments.user_id');

        if ($search !== null) {
            $query->whereHas('user', fn ($u) => $u
                ->where('firstname', 'like', '%'.$search.'%')
                ->orWhere('lastname', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%'));
        }

        return $query;
    }

    /**
     * @param  Collection<int, DonorTier>  $tiers  Ordered ascending by minimum_amount.
     */
    private function tierFor(Collection $tiers, string $total): ?object
    {
        return $tiers->filter(fn ($tier) => (float) $tier->minimum_amount <= (float) $total)->last();
    }

    /**
     * This donor's own Institution id (for their tier lookup), resolved from their
     * tertiary_institution_id; null if they have none or it isn't an onboarded institution -
     * the repository falls back to the platform-wide default tiers in that case.
     */
    private function institutionIdFor(User $user): ?int
    {
        if ($user->tertiary_institution_id === null) {
            return null;
        }

        return $this->institutionRepository
            ->institutionIdsByTertiaryInstitutionIds([$user->tertiary_institution_id])[$user->tertiary_institution_id] ?? null;
    }

    /**
     * Batch version of {@see self::institutionIdFor()} plus their active tier sets in one go,
     * for the leaderboard's page of (potentially many different institutions') donors.
     *
     * @param  Collection<int, User>  $users
     * @return array{institutionIds: array<int, int>, tiers: array<int, Collection<int, DonorTier>>}
     */
    private function tiersForUsers(Collection $users): array
    {
        $tertiaryIds = $users->pluck('tertiary_institution_id')->filter()->unique()->values()->all();
        $institutionIds = $this->institutionRepository->institutionIdsByTertiaryInstitutionIds($tertiaryIds);

        $tiers = $this->donorTierRepository->activeOrderedByThresholdForInstitutions(array_values($institutionIds));

        return ['institutionIds' => $institutionIds, 'tiers' => $tiers];
    }

    /**
     * @param  array{institutionIds: array<int, int>, tiers: array<int, Collection<int, DonorTier>>}  $batch
     * @return Collection<int, DonorTier>
     */
    private function tiersForUser(User $user, array $batch): Collection
    {
        $institutionId = $user->tertiary_institution_id !== null
            ? ($batch['institutionIds'][$user->tertiary_institution_id] ?? null)
            : null;

        $tiers = $institutionId !== null ? ($batch['tiers'][$institutionId] ?? null) : null;

        if ($tiers instanceof Collection && $tiers->isNotEmpty()) {
            return $tiers->values();
        }

        // No tiers of this institution's own (or none resolvable) - same default-tier fallback
        // DonorTierRepository::activeOrderedByThreshold() uses for a single lookup.
        return $this->donorTierRepository->activeOrderedByThreshold(null);
    }
}
