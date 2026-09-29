<?php

namespace App\Repositories\Donation;

use App\Enums\PaymentStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Campaign;
use App\Models\DonationPayment;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DonationPaymentRepository implements DonationPaymentRepositoryInterface
{
    private const MAX_EXPORT_ROWS = 5000;

    public function create(array $data): DonationPayment
    {
        return DonationPayment::create($data);
    }

    public function findByReference(string $reference): ?DonationPayment
    {
        return DonationPayment::query()
            ->with(['donation.campaign', 'user'])
            ->where('gateway_reference', $reference)
            ->first();
    }

    public function findByReferenceForUpdate(string $reference): ?DonationPayment
    {
        return DonationPayment::query()
            ->with(['donation.campaign', 'user'])
            ->where('gateway_reference', $reference)
            ->lockForUpdate()
            ->first();
    }

    public function markFailed(DonationPayment $payment): DonationPayment
    {
        $payment->forceFill(['status' => PaymentStatusEnum::FAILED->value])->save();

        return $payment;
    }

    public function markSuccessful(DonationPayment $payment, array $data): DonationPayment
    {
        $payment->forceFill(array_merge(['status' => PaymentStatusEnum::SUCCESSFUL->value], $data))->save();

        return $payment;
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->userPaymentsQuery($userId, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonationPayment>, 1: bool}
     */
    public function exportForUser(int $userId, array $filters): array
    {
        $query = $this->userPaymentsQuery($userId, $filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DonationPayment>
     */
    private function userPaymentsQuery(int $userId, array $filters): Builder
    {
        $query = DonationPayment::query()
            ->select('donation_payments.*')
            ->with(['donation.campaign'])
            ->where('donation_payments.user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('donation_payments.status', $filters['status'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('donation.campaign', fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'donation_payments.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('donations', 'donations.id', '=', 'donation_payments.donation_id')
                ->leftJoin('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
                ->orderBy('campaigns.title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('donation_payments.amount', $direction),
        ], 'donation_payments.created_at');

        return $query;
    }

    public function findByUuidForUser(int $userId, string $uuid): ?DonationPayment
    {
        return DonationPayment::query()
            ->with(['donation.campaign'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function sumSuccessfulForUser(int $userId, ?string $from, ?string $to): string
    {
        // Summing across currencies isn't meaningful (₦ + $ isn't a real number); scoped to
        // NGN, same call made for the Recognition Wall leaderboard.
        return (string) DonationPayment::query()
            ->where('user_id', $userId)
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->when($from !== null, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->sum('amount');
    }

    public function distinctCampaignGoalTotalForUser(int $userId): string
    {
        $campaignIds = DonationPayment::query()
            ->where('donation_payments.user_id', $userId)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->distinct()
            ->pluck('donations.campaign_id');

        return (string) Campaign::query()->withoutGlobalScope(TenantScope::class)->whereIn('id', $campaignIds)->where('currency', 'NGN')->sum('goal_amount');
    }

    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DonationPayment::query()
            ->select('donation_payments.*')
            ->with(['donation.user.tertiaryInstitution'])
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donations.campaign_id', $campaignId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('donation_payments.status', $filters['status'])
            )
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('donations.guest_name', 'like', '%'.$search.'%')
                        ->orWhere('donations.guest_email', 'like', '%'.$search.'%')
                        ->orWhereHas('donation.user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'donation_payments.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'value' => fn ($query, string $direction) => $query->orderBy('donation_payments.amount', $direction),
        ], 'donation_payments.created_at');

        return $query->paginate($perPage);
    }

    public function paginateRecentDonorsForCampaign(int $campaignId, int $perPage): LengthAwarePaginator
    {
        return DonationPayment::query()
            ->select('donation_payments.*')
            ->with('donation.user')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donations.campaign_id', $campaignId)
            ->where('donations.is_anonymous', false)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->orderByDesc('donation_payments.paid_at')
            ->paginate($perPage);
    }

    public function distinctSuccessfulDonorUserIdsForCampaign(int $campaignId, ?string $from, ?string $to): array
    {
        return DonationPayment::query()
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donations.campaign_id', $campaignId)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->whereNotNull('donation_payments.user_id')
            ->when($from !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '<=', $to))
            ->distinct()
            ->pluck('donation_payments.user_id')
            ->all();
    }

    public function sumSuccessfulForCampaign(int $campaignId, ?string $from, ?string $to): string
    {
        return (string) DonationPayment::query()
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donations.campaign_id', $campaignId)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->when($from !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '<=', $to))
            ->sum('donation_payments.amount');
    }

    public function sumSuccessfulForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): string
    {
        return (string) DonationPayment::query()
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->join('users', 'users.id', '=', 'donations.user_id')
            ->where('donations.campaign_id', $campaignId)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('users.tertiary_institution_id', $tertiaryInstitutionId)
            ->sum('donation_payments.amount');
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminPaymentsQuery($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonationPayment>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        $query = $this->adminPaymentsQuery($filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DonationPayment>
     */
    private function adminPaymentsQuery(array $filters): Builder
    {
        $query = DonationPayment::query()
            ->select('donation_payments.*')
            ->with(['donation.campaign', 'donation.user.tertiaryInstitution'])
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('donation_payments.status', $filters['status'])
            )
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('donations.guest_name', 'like', '%'.$search.'%')
                        ->orWhere('donations.guest_email', 'like', '%'.$search.'%')
                        ->orWhere('donation_payments.gateway_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('donation.campaign', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
                        ->orWhereHas('donation.user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'donation_payments.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
                ->orderBy('campaigns.title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('donation_payments.amount', $direction),
        ], 'donation_payments.created_at');

        return $query;
    }

    public function findByUuidForAdmin(string $uuid): ?DonationPayment
    {
        return DonationPayment::query()
            ->with(['donation.campaign', 'donation.user.tertiaryInstitution'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function sumSuccessfulForAdmin(?string $from, ?string $to, ?string $campaignType = null): string
    {
        return (string) DonationPayment::query()
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->when($campaignType !== null, fn ($query) => $this->ofCampaignType($query, $campaignType))
            ->when($from !== null, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->sum('amount');
    }

    public function countSuccessfulForAdmin(?string $from, ?string $to): int
    {
        return (int) DonationPayment::query()
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->when($from !== null, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->count();
    }

    public function distinctSuccessfulDonorUserIdsForAdmin(?string $from, ?string $to, ?string $campaignType = null): array
    {
        return DonationPayment::query()
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->whereNotNull('user_id')
            ->when($campaignType !== null, fn ($query) => $this->ofCampaignType($query, $campaignType))
            ->when($from !== null, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->distinct()
            ->pluck('user_id')
            ->all();
    }

    public function distinctCampaignGoalTotalForAdmin(): string
    {
        $campaignIds = DonationPayment::query()
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->distinct()
            ->pluck('donations.campaign_id');

        // $campaignIds already came from a tenant-scoped payment query, which correctly includes any
        // standard campaign this tenant's own donors gave to; re-applying Campaign's tenant scope here
        // would wrongly drop those (a standard campaign never has a campaign_institutions row for anyone).
        return (string) Campaign::query()->withoutGlobalScope(TenantScope::class)->whereIn('id', $campaignIds)->where('currency', 'NGN')->sum('goal_amount');
    }

    public function resolveTierUpgradeDate(int $userId, string $thresholdAmount): ?CarbonInterface
    {
        $running = '0';

        $payments = DonationPayment::query()
            ->where('user_id', $userId)
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->orderBy('paid_at')
            ->get(['amount', 'paid_at']);

        foreach ($payments as $payment) {
            $running = bcadd($running, (string) $payment->amount, 2);
            if (bccomp($running, $thresholdAmount, 2) >= 0) {
                return $payment->paid_at;
            }
        }

        return null;
    }

    public function totalsByInstitutions(array $tertiaryInstitutionIds): array
    {
        if ($tertiaryInstitutionIds === []) {
            return [];
        }

        $rows = DonationPayment::query()
            ->join('users', 'users.id', '=', 'donation_payments.user_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->whereIn('users.tertiary_institution_id', $tertiaryInstitutionIds)
            ->groupBy('users.tertiary_institution_id')
            ->selectRaw('users.tertiary_institution_id as institution_id, sum(donation_payments.amount) as total, count(distinct donation_payments.user_id) as donors')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->institution_id] = ['total' => (string) $row->total, 'donors' => (int) $row->donors];
        }

        return $totals;
    }

    public function statsByCampaigns(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $rows = DonationPayment::query()
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->whereIn('donations.campaign_id', $campaignIds)
            ->groupBy('donations.campaign_id')
            ->selectRaw('donations.campaign_id as campaign_id, count(*) as donations, count(distinct coalesce(donation_payments.user_id, donations.guest_email)) as donors')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->campaign_id] = ['donations' => (int) $row->donations, 'donors' => (int) $row->donors];
        }

        return $stats;
    }

    /**
     * @param  Builder<DonationPayment>  $query
     */
    private function ofCampaignType(Builder $query, string $campaignType): void
    {
        $query->whereIn('donation_payments.donation_id', DB::table('donations')
            ->join('campaigns', 'campaigns.id', '=', 'donations.campaign_id')
            ->where('campaigns.type', $campaignType)
            ->select('donations.id'));
    }

    public function countDonorsForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): int
    {
        return (int) DonationPayment::query()
            ->join('donations', 'donations.id', '=', 'donation_payments.donation_id')
            ->join('users', 'users.id', '=', 'donations.user_id')
            ->where('donations.campaign_id', $campaignId)
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('users.tertiary_institution_id', $tertiaryInstitutionId)
            ->distinct()
            ->count('donations.user_id');
    }

    public function totalsByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        // Summing across currencies isn't meaningful (₦ + $ isn't a real number), same NGN-only
        // scoping as sumSuccessfulForUser().
        $rows = DonationPayment::query()
            ->whereIn('user_id', $userIds)
            ->where('status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('currency', 'NGN')
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as payments, sum(amount) as total')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->user_id] = ['count' => (int) $row->payments, 'total' => (string) $row->total];
        }

        return $totals;
    }

    public function donorCountsByConstituentType(?string $from, ?string $to): array
    {
        $rows = DonationPayment::query()
            ->join('users', 'users.id', '=', 'donation_payments.user_id')
            ->where('donation_payments.status', PaymentStatusEnum::SUCCESSFUL->value)
            ->where('donation_payments.currency', 'NGN')
            ->when($from !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('donation_payments.paid_at', '<=', $to))
            ->groupBy('users.constituent_type')
            ->selectRaw('users.constituent_type, count(distinct donation_payments.user_id) as total')
            ->pluck('total', 'constituent_type');

        return [
            'alumni' => (int) ($rows['alumni'] ?? 0),
            'non_alumni' => (int) ($rows['non_alumni'] ?? 0),
            'organization' => (int) ($rows['organization'] ?? 0),
        ];
    }
}
