<?php

namespace App\Services\Dashboard;

use App\Enums\AdminScopeEnum;
use App\Exceptions\ApiException;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Carbon\CarbonInterface;

/**
 * Institution admin's own "Dashboard" home screen: every figure here is scoped to the caller's
 * institution automatically, constituents/campaigns/donations/events all carry BelongsToTenant
 * (events via the OwnedByInstitution flavour of it), so calling the same repository methods
 * NationalDashboardService uses for NHEF just narrows to this institution's own records instead.
 */
class InstitutionDashboardService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly DonationPaymentRepositoryInterface $donationPaymentRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventRegistrationRepositoryInterface $eventRegistrationRepository,
    ) {}

    private function assertInstitutionScope(): void
    {
        if (AdminScopeEnum::current() !== AdminScopeEnum::INSTITUTION) {
            throw new ApiException('This dashboard is only available to institution admins.', 403);
        }
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

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        $this->assertInstitutionScope();

        $types = $this->userRepository->countByConstituentType(null, null);
        $totalRaised = $this->donationPaymentRepository->sumSuccessfulForAdmin(null, null);
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
            'donation_intelligence' => $this->donationIntelligence($totalRaised, $filters),
            'alumni_intelligence' => $this->alumniIntelligence($types, $filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function donationIntelligence(string $totalRaised, array $filters): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $raisedThisMonth = $this->donationPaymentRepository->sumSuccessfulForAdmin($monthStart, $monthEnd);

        $totalDonors = count($this->donationPaymentRepository->distinctSuccessfulDonorUserIdsForAdmin(null, null));
        $totalPayments = $this->donationPaymentRepository->countSuccessfulForAdmin(null, null);
        $averageDonation = $totalPayments > 0 ? bcdiv($totalRaised, (string) $totalPayments, 2) : '0.00';
        $donorTypes = $this->donationPaymentRepository->donorCountsByConstituentType(null, null);

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
        $rows = $this->donationPaymentRepository->dailyTotalsByConstituentType($window['start'], $window['end']);

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
     * @param  array{alumni: int, non_alumni: int, organization: int}  $types
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function alumniIntelligence(array $types, array $filters): array
    {
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
     * @param  array<string, mixed>  $filters
     * @return array{upcoming: int, completed: int, average_attendance: string, distribution_trend: array<string, mixed>, attendance_trend: array<string, mixed>}
     */
    public function eventIntelligence(array $filters = []): array
    {
        $this->assertInstitutionScope();

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
}
