<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\CampaignDonationListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignPledgeListRequest;
use App\Http\Requests\Admin\Campaigns\CreateCampaignRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Resources\Admin\CampaignAdminResource;
use App\Http\Resources\Admin\CampaignDetailResource;
use App\Http\Resources\Admin\CampaignDonationResource;
use App\Http\Resources\Admin\CampaignPledgeResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    public function store(CreateCampaignRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Campaign created successfully.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@store');
        }
    }

    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Campaigns retrieved.', $this->paginatedPayload($paginator, CampaignAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $campaign = $this->campaignService->findForAdmin($uuid);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));

            return JsonResponser::send(false, 'Campaign retrieved.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@show');
        }
    }

    public function update(UpdateCampaignRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->update($uuid, $request->validated(), $admin, $request);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));

            return JsonResponser::send(false, 'Campaign updated.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@update');
        }
    }

    public function pause(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->pause($uuid, $admin, $request);

            return JsonResponser::send(false, 'Campaign paused.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@pause');
        }
    }

    public function resume(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->resume($uuid, $admin, $request);

            return JsonResponser::send(false, 'Campaign resumed.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@resume');
        }
    }

    public function donations(CampaignDonationListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listDonations($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donations retrieved.', $this->paginatedPayload($paginator, CampaignDonationResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donations');
        }
    }

    public function pledges(CampaignPledgeListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listPledges($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign pledges retrieved.', $this->paginatedPayload($paginator, CampaignPledgeResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@pledges');
        }
    }

    public function donationsOverview(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $overview = $this->campaignService->donationsOverview($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donation overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donationsOverview');
        }
    }

    public function donorBreakdown(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $breakdown = $this->campaignService->donorBreakdown($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donor breakdown retrieved.', $breakdown);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donorBreakdown');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
