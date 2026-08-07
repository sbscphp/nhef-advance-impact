<?php

namespace App\Repositories\Contracts\Donation;

use App\Models\DonationPayment;
use Illuminate\Pagination\LengthAwarePaginator;

interface DonationPaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DonationPayment;

    public function findByReference(string $reference): ?DonationPayment;

    public function markFailed(DonationPayment $payment): DonationPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(DonationPayment $payment, array $data): DonationPayment;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuidForUser(int $userId, string $uuid): ?DonationPayment;

    public function sumSuccessfulForUser(int $userId, ?string $from, ?string $to): string;

    public function distinctCampaignGoalTotalForUser(int $userId): string;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Registered donors (user_id not null) with a successful payment to this campaign, optionally
     * scoped to a date window; feeds the "Donor Breakdown" tier chart (tiers are lifetime, so the
     * window only decides which donors are counted, not which of their payments).
     *
     * @return list<int>
     */
    public function distinctSuccessfulDonorUserIdsForCampaign(int $campaignId, ?string $from, ?string $to): array;

    /**
     * Sum of successful payments for a campaign, optionally scoped to a date window; feeds the
     * "Donation" tab overview (Total Donation Received).
     */
    public function sumSuccessfulForCampaign(int $campaignId, ?string $from, ?string $to): string;
}
