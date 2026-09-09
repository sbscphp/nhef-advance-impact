<?php

namespace App\Services\Dashboard;

use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Support\Money;

class AdminDashboardService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly MentorProfileRepositoryInterface $mentorProfileRepository,
        private readonly DonationPaymentRepositoryInterface $donationPaymentRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $totalRaised = $this->donationPaymentRepository->sumSuccessfulForAdmin(null, null);
        $totalMentors = $this->mentorProfileRepository->countActive();
        $alumniStatus = $this->userRepository->countByStatus(null, null);

        return [
            'national_snapshot' => [
                'total_alumni' => $alumniStatus['all'],
                'total_institutions' => $this->institutionRepository->count(false),
                'total_events' => $this->eventRepository->countByStatusBuckets(null, null)['all'],
                'active_campaigns' => $this->campaignRepository->countActive(),
                'total_mentors' => $totalMentors,
                'total_raised' => $totalRaised,
                'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
            ],
            'donation_intelligence' => $this->donationIntelligence($totalRaised),
            'alumni_intelligence' => [
                ...$alumniStatus,
                'verified' => $this->userRepository->countVerified(null, null),
                'total_mentors' => $totalMentors,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donationIntelligence(string $totalRaised): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $raisedThisMonth = $this->donationPaymentRepository->sumSuccessfulForAdmin($monthStart, $monthEnd);

        $totalDonors = count($this->donationPaymentRepository->distinctSuccessfulDonorUserIdsForAdmin(null, null));
        $totalPayments = $this->donationPaymentRepository->countSuccessfulForAdmin(null, null);
        $averageDonation = $totalPayments > 0 ? bcdiv($totalRaised, (string) $totalPayments, 2) : '0.00';

        return [
            'total_raised' => $totalRaised,
            'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
            'raised_this_month' => $raisedThisMonth,
            'raised_this_month_formatted' => Money::format($raisedThisMonth, 'NGN'),
            'total_donors' => $totalDonors,
            'average_donation' => $averageDonation,
            'average_donation_formatted' => Money::format($averageDonation, 'NGN'),
        ];
    }
}
