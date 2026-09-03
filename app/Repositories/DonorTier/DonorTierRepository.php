<?php

namespace App\Repositories\DonorTier;

use App\Enums\PaymentStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\DonationPayment;
use App\Models\DonorTier;
use App\Models\User;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DonorTierRepository implements DonorTierRepositoryInterface
{
    private const MAX_EXPORT_ROWS = 5000;

    public function allOrderedByThreshold(): Collection
    {
        return DonorTier::query()->orderBy('minimum_amount')->get();
    }

    public function findForAmount(string $amount): ?DonorTier
    {
        return DonorTier::query()
            ->where('minimum_amount', '<=', $amount)
            ->orderByDesc('minimum_amount')
            ->first();
    }

    public function create(array $data): DonorTier
    {
        return DonorTier::create($data);
    }

    public function findByUuid(string $uuid): ?DonorTier
    {
        return DonorTier::query()->where('uuid', $uuid)->first();
    }

    public function update(DonorTier $tier, array $data): DonorTier
    {
        $tier->fill($data)->save();

        return $tier;
    }

    public function delete(DonorTier $tier): bool
    {
        return (bool) $tier->delete();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonorTier>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        $query = $this->adminListQuery($filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DonorTier>
     */
    private function adminListQuery(array $filters): Builder
    {
        $query = DonorTier::query()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('is_active', $filters['filters']['status'] === 'active')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('minimum_amount', $direction),
        ], 'updated_at');

        return $query;
    }

    public function statsForRange(string $minimum, ?string $maximum): array
    {
        $userIds = $this->bracketAggregateQuery($minimum, $maximum)->pluck('donation_payments.user_id');

        return [
            'alumni_count' => $userIds->count(),
            'institution_count' => User::query()
                ->whereIn('id', $userIds)
                ->whereNotNull('university')
                ->distinct('university')
                ->count('university'),
        ];
    }

    public function paginateUsersInRange(string $minimum, ?string $maximum, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->usersInRangeQuery($minimum, $maximum, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportUsersInRange(string $minimum, ?string $maximum, array $filters): array
    {
        $query = $this->usersInRangeQuery($minimum, $maximum, $filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function usersInRangeQuery(string $minimum, ?string $maximum, array $filters): Builder
    {
        $aggregate = $this->bracketAggregateQuery($minimum, $maximum);

        return User::query()
            ->select('users.*')
            ->joinSub($aggregate, 'donor_totals', 'donor_totals.user_id', '=', 'users.id')
            ->addSelect(['donor_totals.total as lifetime_total', 'donor_totals.payments_count as payments_count'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('users.firstname', 'like', '%'.$search.'%')
                        ->orWhere('users.lastname', 'like', '%'.$search.'%')
                        ->orWhere('users.email', 'like', '%'.$search.'%');
                });
            })
            ->when(
                filled($filters['institution'] ?? null),
                fn ($query) => $query->where('users.university', $filters['institution'])
            )
            ->orderByDesc('donor_totals.total');
    }

    /**
     * One row per donor with a successful NGN payment, their lifetime total and payment count,
     * restricted to totals in [$minimum, $maximum). $maximum null means unbounded above.
     *
     * @return Builder<DonationPayment>
     */
    private function bracketAggregateQuery(string $minimum, ?string $maximum): Builder
    {
        return DonationPayment::query()
            ->select('donation_payments.user_id')
            ->selectRaw('SUM(donation_payments.amount) as total')
            ->selectRaw('COUNT(*) as payments_count')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->where('donations.is_anonymous', false)
            ->whereNotNull('donation_payments.user_id')
            ->groupBy('donation_payments.user_id')
            ->havingRaw('SUM(donation_payments.amount) >= ?', [$minimum])
            ->when(
                $maximum !== null,
                fn ($query) => $query->havingRaw('SUM(donation_payments.amount) < ?', [$maximum])
            );
    }
}
