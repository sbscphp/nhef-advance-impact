<?php

namespace App\Services\ConstituentManagement;

use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public "Join Your University Community" directory: onboarded institutions with community
 * stats, browsed by anonymous website visitors (no account, no admin permissions involved).
 */
class InstitutionDirectoryService
{
    public function __construct(
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly InstitutionStatsLoader $statsLoader,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->institutionRepository->paginatePublic($filters, $perPage);

        $institutions = collect($paginator->items());
        $this->statsLoader->attach($institutions);

        $institutionIds = $institutions->pluck('id')->all();
        $activeCampaigns = $this->campaignInstitutionRepository->countActiveByInstitutions($institutionIds);
        $activeEvents = $this->eventRepository->countActiveByInstitutions($institutionIds);
        foreach ($institutions as $institution) {
            $institution->setAttribute('active_campaigns_count', $activeCampaigns[$institution->id] ?? 0);
            $institution->setAttribute('active_events_count', $activeEvents[$institution->id] ?? 0);
        }

        return $paginator;
    }
}
