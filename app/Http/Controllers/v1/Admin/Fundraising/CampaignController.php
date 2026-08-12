<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Enums\CampaignTypeEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\AddCampaignInstitutionRequest;
use App\Http\Requests\Admin\Campaigns\CampaignDonationListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignInstitutionListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignPledgeListRequest;
use App\Http\Requests\Admin\Campaigns\CreateCampaignRequest;
use App\Http\Requests\Admin\Campaigns\CreateNationalGivingDayCampaignRequest;
use App\Http\Requests\Admin\Campaigns\SyncCampaignInstitutionsRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignInstitutionRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Resources\Admin\CampaignAdminResource;
use App\Http\Resources\Admin\CampaignDetailResource;
use App\Http\Resources\Admin\CampaignDonationResource;
use App\Http\Resources\Admin\CampaignInstitutionResource;
use App\Http\Resources\Admin\CampaignPledgeResource;
use App\Models\Admin;
use App\Models\Campaign;
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

    /**
     * @hideFromAPIDocumentation
     */
    public function storeNationalGivingDay(CreateNationalGivingDayCampaignRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->createNationalGivingDay($request->validated(), $admin, $request);
            $this->applyNationalGivingDayTotals($campaign);

            return JsonResponser::send(false, 'National Giving Day campaign created successfully.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@storeNationalGivingDay');
        }
    }

    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginateForAdmin($request->validated());
            foreach ($paginator->items() as $campaign) {
                $this->applyNationalGivingDayTotals($campaign);
            }

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
            $this->applyNationalGivingDayTotals($campaign);

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
            $this->applyNationalGivingDayTotals($campaign);

            return JsonResponser::send(false, 'Campaign updated.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@update');
        }
    }

    /** NGD campaigns store no top-level goal/raised/currency; overlay the live NGN aggregate. */
    private function applyNationalGivingDayTotals(Campaign $campaign): void
    {
        if ($campaign->type !== CampaignTypeEnum::NATIONAL_GIVING_DAY->value) {
            return;
        }

        $totals = $this->campaignService->nationalGivingDayTotals($campaign);
        $campaign->setAttribute('goal_amount', $totals['goal_amount']);
        $campaign->setAttribute('raised_amount', $totals['raised_amount']);
        $campaign->setAttribute('currency', $totals['currency']);
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

    /**
     * @hideFromAPIDocumentation
     */
    public function institutions(CampaignInstitutionListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listInstitutions($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign institutions retrieved.', $this->paginatedPayload($paginator, CampaignInstitutionResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@institutions');
        }
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function addInstitution(AddCampaignInstitutionRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitution = $this->campaignService->addInstitution($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution added to campaign.', CampaignInstitutionResource::make($campaignInstitution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@addInstitution');
        }
    }

    /**
     * Full-list replace: any institution left out of `institutions` is deleted from the campaign.
     *
     * @hideFromAPIDocumentation
     */
    public function syncInstitutions(SyncCampaignInstitutionsRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitutions = $this->campaignService->syncInstitutions($uuid, $request->validated()['institutions'], $admin, $request);

            return JsonResponser::send(false, 'Campaign institutions updated.', CampaignInstitutionResource::collection($campaignInstitutions)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@syncInstitutions');
        }
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function updateInstitution(UpdateCampaignInstitutionRequest $request, string $uuid, string $institutionUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitution = $this->campaignService->updateInstitution($uuid, $institutionUuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution allocation updated.', CampaignInstitutionResource::make($campaignInstitution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@updateInstitution');
        }
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function removeInstitution(Request $request, string $uuid, string $institutionUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->campaignService->removeInstitution($uuid, $institutionUuid, $admin, $request);

            return JsonResponser::send(false, 'Institution removed from campaign.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@removeInstitution');
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
