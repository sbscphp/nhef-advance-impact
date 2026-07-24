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
}
