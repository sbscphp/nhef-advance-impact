<?php

namespace App\Services\Recognition;

use App\Enums\PaymentStatusEnum;
use App\Models\DonationPayment;
use App\Models\DonorTier;
use App\Models\User;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Donor recognition wall (BRD REC-01/REC-02). Scoped to successful, NGN-denominated donation
 * payments only; summing across currencies isn't meaningful, and there's no FX-rate source
 * in this codebase. Anonymous donations never appear on the public leaderboard.
 */
class RecognitionService
{
    public function __construct(
        private readonly DonorTierRepositoryInterface $donorTierRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function leaderboard(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $paginator = $this->baseAggregateQuery()
            ->orderByDesc('total')
            ->paginate($perPage);

        $tiers = $this->donorTierRepository->allOrderedByThreshold();
        $users = User::query()
            ->whereIn('id', collect($paginator->items())->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $startRank = ($paginator->currentPage() - 1) * $paginator->perPage() + 1;

        $entries = collect($paginator->items())->values()->map(function ($row, int $index) use ($users, $tiers, $startRank) {
            $user = $users->get($row->user_id);

            return (object) [
                'rank' => $startRank + $index,
                'donor_name' => $user?->displayName() ?? 'Donor',
                'tier' => $this->tierFor($tiers, (string) $row->total)?->name,
                'total' => (string) $row->total,
            ];
        });

        $paginator->setCollection($entries);

        return $paginator;
    }

    /**
     * @return array{rank: ?int, tier: ?string, total: string, total_formatted: string, next_tier: ?string, amount_to_next_tier: ?string, amount_to_next_tier_formatted: ?string}
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

        $tiers = $this->donorTierRepository->allOrderedByThreshold();
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
            'total' => $total,
            'total_formatted' => Money::format($total, 'NGN'),
            'next_tier' => $nextTier?->name,
            'amount_to_next_tier' => $amountToNextTier,
            'amount_to_next_tier_formatted' => $amountToNextTier !== null ? Money::format($amountToNextTier, 'NGN') : null,
        ];
    }

    /**
     * Shared base for both the leaderboard and the rank-comparison query in myRank(): one row
     * per user, their lifetime NGN total from successful, non-anonymous donations. Guests
     * (user_id null) are excluded automatically; there's no account to rank.
     */
    private function baseAggregateQuery(): Builder
    {
        return DonationPayment::query()
            ->select('donation_payments.user_id')
            ->selectRaw('SUM(donation_payments.amount) as total')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->where('donations.is_anonymous', false)
            ->whereNotNull('donation_payments.user_id')
            ->groupBy('donation_payments.user_id');
    }

    /**
     * @param  Collection<int, DonorTier>  $tiers  Ordered ascending by minimum_amount.
     */
    private function tierFor(Collection $tiers, string $total): ?object
    {
        return $tiers->filter(fn ($tier) => (float) $tier->minimum_amount <= (float) $total)->last();
    }
}
