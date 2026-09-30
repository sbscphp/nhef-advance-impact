<?php

namespace App\Http\Controllers\v1\Admin\Dashboard;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\v1\Admin\Concerns\RespondsWithAuditTimeline;
use App\Http\Requests\Admin\Dashboard\DashboardSectionRequest;
use App\Responser\JsonResponser;
use App\Services\Audit\AuditTrailQueryService;
use App\Services\Dashboard\DashboardService;
use App\Support\ListingQuery;

class AdminDashboardController extends Controller
{
    use RespondsWithAuditTimeline;

    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AuditTrailQueryService $auditTrailQuery,
    ) {}

    /** NHEF: "National Snapshot". Institution Admin: the dashboard's own top stat-card row. */
    public function nationalSnapshot(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Dashboard snapshot retrieved.', $this->dashboardService->snapshot($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@nationalSnapshot');
        }
    }

    /** NHEF-only: cross-institution active campaign progress. */
    public function campaignTracking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Active campaign tracking retrieved.', $this->dashboardService->campaignTracking($request->validated())->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@campaignTracking');
        }
    }

    /** NHEF: "Event Tracking" (cross-institution list). Institution Admin: "Event Intelligence" (own aggregate + trends). */
    public function eventTracking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Event tracking retrieved.', $this->dashboardService->eventTracking($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@eventTracking');
        }
    }

    /** NHEF: "Institution ranking" (every institution). Institution Admin: "Alumni Intelligence" (their own). */
    public function institutionRanking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Institution ranking retrieved.', $this->dashboardService->institutionRanking($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@institutionRanking');
        }
    }

    /** Institution-only: "Donation Intelligence". No Super Admin dashboard card to merge it with. */
    public function donationIntelligence(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Donation intelligence retrieved.', $this->dashboardService->donationIntelligence($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@donationIntelligence');
        }
    }

    /** Shared: identical "Live Activity" widget on both dashboards, already scope-safe via the underlying audit query. */
    public function liveActivity(DashboardSectionRequest $request)
    {
        try {
            $listing = ListingQuery::fromValidated($request->validated(), defaultPerPage: 20);

            return $this->auditTimelineResponse($this->auditTrailQuery, $listing, 'Live activity retrieved.');
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@liveActivity');
        }
    }
}
