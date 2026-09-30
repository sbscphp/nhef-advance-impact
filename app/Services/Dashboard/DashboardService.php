<?php

namespace App\Services\Dashboard;

use App\Enums\AdminScopeEnum;
use App\Exceptions\ApiException;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\CampaignInstitution;
use App\Models\Event;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The admin Dashboard home screen, both flavours. Where the Figma shows the same card for both
 * (Snapshot, Event stats, Alumni/Institution breakdown), one method here branches on
 * {@see AdminScopeEnum::current()} and returns whichever shape that screen needs, instead of
 * two near-duplicate services with a matching pair of near-duplicate endpoints (was
 * NationalDashboardService + InstitutionDashboardService + a third, stale, bundled
 * AdminDashboardService::overview(), 2026-09-30). Cards with no equivalent on the other side
 * (Campaign Tracking, Donation Intelligence) stay single-scope, guarded explicitly.
 *
 * Everything NHEF-facing is an institution-level aggregate: no individual donor, alumnus or
 * donation amount is exposed there. Money fields throughout go through {@see ViewerVisibility::money()}.
 */
class DashboardService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventRegistrationRepositoryInterface $eventRegistrationRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
    ) {}

    /**
     * Shared: "National Snapshot" (NHEF) / the dashboard's own top stat-card row (Institution).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function snapshot(array $filters): array
    {
        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION) {
            return $this->institutionSnapshot();
        }

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
     * @return array<string, mixed>
     */
    private function institutionSnapshot(): array
    {
        $types = $this->userRepository->countByConstituentType(null, null);
        $totalRaised = $this->paymentRepository->sumSuccessfulForAdmin(null, null);
        $events = $this->eventRepository->countByStatusBuckets(null, null);

        return [
            'alumni_count' => $types['alumni'],
            'non_alumni_count' => $types['non_alumni'],
            'organisation_count' => $types['organization'],
            'active_campaigns' => $this->campaignRepository->countActive(),
            'active_events' => $events['scheduled'] + $events['ongoing'],
            ...ViewerVisibility::money([
                'total_raised' => $totalRaised,
                'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
            ]),
        ];
    }

    /**
     * NHEF-only: cross-institution active campaign progress. No Institution Admin equivalent
     * exists on their dashboard (their own campaigns show only as a count, in snapshot()).
     *
     * @param  array<string, mixed>  $filters
     */
    public function campaignTracking(array $filters): LengthAwarePaginator
    {
        $this->assertNhefScope('This dashboard card is only available to NHEF-level accounts.');

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
     * Shared: "Event Tracking" (NHEF, a cross-institution list) / "Event Intelligence"
     * (Institution, aggregate cards + trend charts for their own events).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function eventTracking(array $filters): array
    {
        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION) {
            return $this->eventIntelligence($filters);
        }

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

        return $paginator->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{upcoming: int, completed: int, average_attendance: string, distribution_trend: array<string, mixed>, attendance_trend: array<string, mixed>}
     */
    private function eventIntelligence(array $filters): array
    {
        $events = $this->eventRepository->countByStatusBuckets(null, null);
        $window = $this->resolveTrendWindow($filters);

        return [
            'upcoming' => $events['scheduled'] + $events['ongoing'],
            'completed' => $events['completed'],
            'average_attendance' => $this->eventRegistrationRepository->averageAttendanceForAdmin(),
            'distribution_trend' => $this->eventDistributionTrend($window),
            'attendance_trend' => $this->eventAttendanceTrend($window),
        ];
    }

    /**
     * Shared: "Institution ranking" (NHEF, every institution ranked) / "Alumni Intelligence"
     * (Institution, their own counts + growth + trend chart) - both are the same alumni/
     * non_alumni/organisation breakdown, one row (own institution) vs many (ranked).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function institutionRanking(array $filters): array
    {
        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION) {
            return $this->alumniIntelligence($filters);
        }

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

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function alumniIntelligence(array $filters): array
    {
        $types = $this->userRepository->countByConstituentType(null, null);
        $totalRegistered = $types['alumni'] + $types['non_alumni'] + $types['organization'];
        $activeLast30Days = $this->userRepository->countActiveSince(now()->subDays(30));

        $currentYear = $this->userRepository->countByConstituentType(now()->subYear(), null);
        $priorYearEnd = now()->subYear();
        $priorYear = $this->userRepository->countByConstituentType($priorYearEnd->copy()->subYear(), $priorYearEnd);
        $currentTotal = $currentYear['alumni'] + $currentYear['non_alumni'] + $currentYear['organization'];
        $priorTotal = $priorYear['alumni'] + $priorYear['non_alumni'] + $priorYear['organization'];
        $growthYoy = $priorTotal > 0 ? round((($currentTotal - $priorTotal) / $priorTotal) * 100, 1) : null;

        return [
            'alumni_count' => $types['alumni'],
            'non_alumni_count' => $types['non_alumni'],
            'organisation_count' => $types['organization'],
            'total_registered' => $totalRegistered,
            'active_last_30_days_percent' => $totalRegistered > 0 ? round(($activeLast30Days / $totalRegistered) * 100) : 0,
            'growth_yoy_percent' => $growthYoy,
            'trend' => $this->onboardedTrend($filters),
        ];
    }

    /**
     * Institution-only: no Super Admin dashboard card shows this, so nothing to merge it with.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function donationIntelligence(array $filters): array
    {
        $this->assertInstitutionScope('This dashboard card is only available to institution admins.');

        $totalRaised = $this->paymentRepository->sumSuccessfulForAdmin(null, null);
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $raisedThisMonth = $this->paymentRepository->sumSuccessfulForAdmin($monthStart, $monthEnd);

        $totalDonors = count($this->paymentRepository->distinctSuccessfulDonorUserIdsForAdmin(null, null));
        $totalPayments = $this->paymentRepository->countSuccessfulForAdmin(null, null);
        $averageDonation = $totalPayments > 0 ? bcdiv($totalRaised, (string) $totalPayments, 2) : '0.00';
        $donorTypes = $this->paymentRepository->donorCountsByConstituentType(null, null);

        return [
            ...ViewerVisibility::money([
                'total_raised' => $totalRaised,
                'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
                'raised_this_month' => $raisedThisMonth,
                'raised_this_month_formatted' => Money::format($raisedThisMonth, 'NGN'),
                'average_donation' => $averageDonation,
                'average_donation_formatted' => Money::format($averageDonation, 'NGN'),
            ]),
            'total_donors' => $totalDonors,
            'organisation_donors' => $donorTypes['organization'],
            'alumni_donors' => $donorTypes['alumni'],
            'non_alumni_donors' => $donorTypes['non_alumni'],
            ...ViewerVisibility::money(['trend' => $this->donationTrend($filters)]),
        ];
    }

    /**
     * "Capital inflow by donor segment": Corporate (organization), Individual (non_alumni) and
     * Alumni, per day.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function donationTrend(array $filters): array
    {
        $window = $this->resolveTrendWindow($filters);
        $rows = $this->paymentRepository->dailyTotalsByConstituentType($window['start'], $window['end']);

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date][$row->constituent_type] = (string) $row->total;
        }

        return [
            'start_date' => $window['start']->toDateString(),
            'end_date' => $window['end']->toDateString(),
            'points' => collect($byDate)->map(fn (array $totals, string $date) => [
                'date' => $date,
                'alumni' => $totals['alumni'] ?? '0',
                'individual' => $totals['non_alumni'] ?? '0',
                'corporate' => $totals['organization'] ?? '0',
            ])->values()->all(),
        ];
    }

    /**
     * "Number of New Alumni Onboarded": alumni vs non-alumni signups per day. Organisation is
     * left off the chart, the Figma only plots Alumni/Non-Alumni.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function onboardedTrend(array $filters): array
    {
        $window = $this->resolveTrendWindow($filters);
        $rows = $this->userRepository->dailyOnboardedByConstituentType($window['start'], $window['end']);

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date][$row->constituent_type] = $row->total;
        }

        return [
            'start_date' => $window['start']->toDateString(),
            'end_date' => $window['end']->toDateString(),
            'points' => collect($byDate)->map(fn (array $counts, string $date) => [
                'date' => $date,
                'alumni' => $counts['alumni'] ?? 0,
                'non_alumni' => $counts['non_alumni'] ?? 0,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $window
     * @return array<string, mixed>
     */
    private function eventDistributionTrend(array $window): array
    {
        $rows = $this->eventRepository->dailyCountByCompletionStatus($window['start'], $window['end']);

        return [
            'start_date' => $window['start']->toDateString(),
            'end_date' => $window['end']->toDateString(),
            'points' => $rows->map(fn ($row) => [
                'date' => $row->date,
                'completed' => $row->completed,
                'upcoming' => $row->upcoming,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $window
     * @return array<string, mixed>
     */
    private function eventAttendanceTrend(array $window): array
    {
        $ids = $this->eventRepository->idsByCompletionStatus($window['start'], $window['end']);
        $rows = $this->eventRegistrationRepository->dailyAttendanceByCompletionStatus(
            $ids['completed'],
            $ids['upcoming'],
            $window['start'],
            $window['end'],
        );

        return [
            'start_date' => $window['start']->toDateString(),
            'end_date' => $window['end']->toDateString(),
            'points' => $rows->map(fn ($row) => [
                'date' => $row->date,
                'completed' => $row->completed,
                'upcoming' => $row->upcoming,
            ])->values()->all(),
        ];
    }

    /**
     * Trend charts default to a trailing 7-day window when no period/date range is given, same
     * default AdminEventService::analytics() uses for its own sales trend.
     *
     * @param  array<string, mixed>  $filters
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    private function resolveTrendWindow(array $filters): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);

        return [
            'start' => $window['start'] ?? now()->subDays(6)->startOfDay(),
            'end' => $window['end'] ?? now()->endOfDay(),
        ];
    }

    private function assertNhefScope(string $message): void
    {
        if (AdminScopeEnum::current() !== AdminScopeEnum::NHEF) {
            throw new ApiException($message, 403);
        }
    }

    private function assertInstitutionScope(string $message): void
    {
        if (AdminScopeEnum::current() !== AdminScopeEnum::INSTITUTION) {
            throw new ApiException($message, 403);
        }
    }
}
