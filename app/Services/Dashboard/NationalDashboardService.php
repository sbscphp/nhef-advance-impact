<?php

namespace App\Services\Dashboard;

use App\Enums\AdminScopeEnum;
use App\Exceptions\ApiException;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\CampaignInstitution;
use App\Models\Event;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Super Admin dashboard cards. Everything is an institution-level aggregate: no individual
 * donor, alumnus or donation amount is exposed here.
 */
class NationalDashboardService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
    ) {}

    private function assertNhefScope(): void
    {
        if (AdminScopeEnum::current() !== AdminScopeEnum::NHEF) {
            throw new ApiException('This dashboard is only available to NHEF-level accounts.', 403);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function nationalSnapshot(array $filters): array
    {
        $this->assertNhefScope();

        $window = ListingFilterRules::resolveDateWindow($filters);
        $types = $this->userRepository->countByConstituentType($window['start'], $window['end']);

        return array_merge(ListingFilterRules::periodMeta($filters), [
            'total_universities' => $this->institutionRepository->countByStatus($window['start'], $window['end'])['all'],
            'alumni_count' => $types['alumni'],
            'non_alumni_count' => $types['non_alumni'],
            'organisation_count' => $types['organization'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function campaignTracking(array $filters): LengthAwarePaginator
    {
        $this->assertNhefScope();

        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));
        $paginator = $this->campaignInstitutionRepository->paginateActiveTracking($filters, $perPage);

        $paginator->setCollection($paginator->getCollection()->map(function (CampaignInstitution $row): array {
            $tertiaryId = $row->institution->tertiary_institution_id;
            $raised = (string) ((float) $this->paymentRepository->sumSuccessfulForCampaignAndInstitution($row->campaign_id, $tertiaryId)
                + (float) $this->pledgeRepository->sumReceivedForCampaignAndInstitution($row->campaign_id, $tertiaryId));

            return [
                'campaign_uuid' => $row->campaign->uuid,
                'institution_uuid' => $row->institution->uuid,
                'university_name' => $row->institution->name,
                'campaign_name' => $row->campaign->title,
                'starts_at' => $row->campaign->starts_at?->toIso8601String(),
                'ends_at' => $row->campaign->ends_at?->toIso8601String(),
                'currency' => $row->currency,
                ...ViewerVisibility::money([
                    'target' => (string) $row->goal_amount,
                    'target_formatted' => Money::format($row->goal_amount, $row->currency),
                    'percent_completed' => $row->progressPercentage($raised),
                ]),
                'donors_count' => $this->paymentRepository->countDonorsForCampaignAndInstitution($row->campaign_id, $tertiaryId),
            ];
        }));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function eventTracking(array $filters): LengthAwarePaginator
    {
        $this->assertNhefScope();

        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));
        $paginator = $this->eventRepository->paginateAdmin($filters, $perPage);

        $paginator->setCollection($paginator->getCollection()->map(fn (Event $event): array => [
            'uuid' => $event->uuid,
            'event_name' => $event->title,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'units_sold' => (int) $event->seats_taken,
            'status' => $event->displayStatus(),
        ]));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function institutionRanking(array $filters): array
    {
        $this->assertNhefScope();

        $window = ListingFilterRules::resolveDateWindow($filters);
        $limit = max(1, min((int) ($filters['limit'] ?? 10), 50));

        $rows = $this->userRepository->rankInstitutionsByConstituents($window['start'], $window['end'], $limit);

        return array_merge(ListingFilterRules::periodMeta($filters), [
            'institutions' => array_map(fn (array $row, int $index): array => [
                'rank' => $index + 1,
                'institution_uuid' => $row['institution_uuid'],
                'university_name' => $row['name'],
                'alumni_count' => $row['alumni'],
                'non_alumni_count' => $row['non_alumni'],
                'organisation_count' => $row['organization'],
            ], $rows, array_keys($rows)),
        ]);
    }
}
