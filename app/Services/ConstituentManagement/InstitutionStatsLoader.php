<?php

namespace App\Services\ConstituentManagement;

use App\Models\Institution;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;

class InstitutionStatsLoader
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly DonationPaymentRepositoryInterface $donationPaymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
    ) {}

    /**
     * Attaches the headline numbers read back by {@see Institution::statsPayload()}. Total pledges
     * is the committed pledge value (cancelled pledges excluded), not the amount paid so far.
     *
     * @param  iterable<Institution>  $institutions
     */
    public function attach(iterable $institutions): void
    {
        $institutions = collect($institutions);
        $tertiaryIds = $institutions->pluck('tertiary_institution_id')->filter()->unique()->values()->all();

        $types = $this->userRepository->countByConstituentTypeForInstitutions($tertiaryIds);
        $donations = $this->donationPaymentRepository->totalsByInstitutions($tertiaryIds);
        $pledges = $this->pledgeRepository->totalCommittedByInstitutions($tertiaryIds);
        $campaigns = $this->campaignInstitutionRepository->countByInstitutions($institutions->pluck('id')->all());

        foreach ($institutions as $institution) {
            $tertiaryId = $institution->tertiary_institution_id;

            $institution->setAttribute('alumni_count', $types[$tertiaryId]['alumni'] ?? 0);
            $institution->setAttribute('non_alumni_count', $types[$tertiaryId]['non_alumni'] ?? 0);
            $institution->setAttribute('organisation_count', $types[$tertiaryId]['organization'] ?? 0);
            $institution->setAttribute('campaigns_count', $campaigns[$institution->id] ?? 0);
            $institution->setAttribute('donors_count', $donations[$tertiaryId]['donors'] ?? 0);
            $institution->setAttribute('total_donations', $donations[$tertiaryId]['total'] ?? '0');
            $institution->setAttribute('total_pledges', $pledges[$tertiaryId] ?? '0');
        }
    }
}
