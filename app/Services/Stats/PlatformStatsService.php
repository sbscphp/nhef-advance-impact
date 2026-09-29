<?php

namespace App\Services\Stats;

use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Support\Money;

/**
 * Public landing page banner stats: unauthenticated, platform-wide, no individual donor data.
 */
class PlatformStatsService
{
    public function __construct(
        private readonly DonationPaymentRepositoryInterface $donationPaymentRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $totalRaised = $this->donationPaymentRepository->sumSuccessfulForAdmin(null, null);
        $verifiedDonors = count($this->donationPaymentRepository->distinctSuccessfulDonorUserIdsForAdmin(null, null));

        return [
            'total_raised' => $totalRaised,
            'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
            'verified_donors' => $verifiedDonors,
            'active_campaigns' => $this->campaignRepository->countActive(),
        ];
    }
}
