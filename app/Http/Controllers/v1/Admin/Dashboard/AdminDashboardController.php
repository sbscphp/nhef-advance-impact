<?php

namespace App\Http\Controllers\v1\Admin\Dashboard;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\v1\Admin\Concerns\RespondsWithAuditTimeline;
use App\Http\Requests\Admin\Dashboard\DashboardSectionRequest;
use App\Responser\JsonResponser;
use App\Services\Audit\AuditTrailQueryService;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Dashboard\NationalDashboardService;
use App\Support\ListingQuery;

class AdminDashboardController extends Controller
{
    use RespondsWithAuditTimeline;

    public function __construct(
        private readonly AdminDashboardService $dashboardService,
        private readonly NationalDashboardService $nationalDashboard,
        private readonly AuditTrailQueryService $auditTrailQuery,
    ) {}

    public function overview()
    {
        try {
            $overview = $this->dashboardService->overview();

            return JsonResponser::send(false, 'Dashboard overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@overview');
        }
    }

    public function nationalSnapshot(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'National snapshot retrieved.', $this->nationalDashboard->nationalSnapshot($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@nationalSnapshot');
        }
    }

    public function campaignTracking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Active campaign tracking retrieved.', $this->nationalDashboard->campaignTracking($request->validated())->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@campaignTracking');
        }
    }

    public function eventTracking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Event tracking retrieved.', $this->nationalDashboard->eventTracking($request->validated())->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@eventTracking');
        }
    }

    public function institutionRanking(DashboardSectionRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Institution ranking retrieved.', $this->nationalDashboard->institutionRanking($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Dashboard\AdminDashboardController@institutionRanking');
        }
    }

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
