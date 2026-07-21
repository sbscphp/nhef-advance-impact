<?php

namespace App\Repositories\Donation;

use App\Enums\PaymentStatusEnum;
use App\Models\Campaign;
use App\Models\DonationPayment;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class DonationPaymentRepository implements DonationPaymentRepositoryInterface
{
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
        return DonationPayment::query()
            ->with(['donation.campaign'])
            ->where('user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('donation.campaign', fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%'))
            )
            ->when(
                filled($filters['from'] ?? null),
                fn ($query) => $query->whereDate('paid_at', '>=', $filters['from'])
            )
            ->when(
                filled($filters['to'] ?? null),
                fn ($query) => $query->whereDate('paid_at', '<=', $filters['to'])
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
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
        // Summing across currencies isn't meaningful (₦ + $ isn't a real number) — scoped to
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

        return (string) Campaign::query()->whereIn('id', $campaignIds)->where('currency', 'NGN')->sum('goal_amount');
    }
}
